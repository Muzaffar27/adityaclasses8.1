<?php

namespace Tests\Feature;

use App\Models\{AutomaticAssessmentBatch, Grade, Lesson, LessonAccess, LessonProgress, LessonQuestionSource, QuestionReview, StudentAssessmentEnrolment, StudentTopicCompletion, Subject, TestPaper, User};
use App\Services\{AutomaticAssessmentService, LessonProgressService};
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class AutomaticAssessmentTest extends TestCase
{
    use UsesIsolatedLearningDatabase;
    private User $student;
    private User $tutor;
    private array $lessons = [];
    private int $grade;
    private int $subject;

    protected function setUp(): void
    {
        parent::setUp(); $this->prepareLearningDatabase(true);
        foreach (['2026_10_19_000001_create_lesson_question_sources_table.php', '2026_10_19_000002_create_question_reviews_table.php',
            '2026_11_01_000001_create_question_corrections_table.php', '2026_11_12_000001_create_saved_test_papers.php',
            '2026_11_12_000003_create_automatic_assessment_tracking.php'] as $file) (require database_path('migrations/'.$file))->up();
        Storage::fake('local');
        $this->student = User::factory()->create(['role' => 'student']); $this->tutor = User::factory()->create(['role' => 'tutor']);
        $this->grade = Grade::create(['name' => 'Grade 8'])->id; $this->subject = Subject::create(['name' => 'Mathematics'])->id;
        LessonAccess::create(['user_id' => $this->student->id, 'grade_id' => $this->grade, 'subject_id' => $this->subject, 'status' => 'accepted']);
        $bank = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $index => $topic) {
            $lesson = Lesson::create(['grade_id' => $this->grade, 'subject_id' => $this->subject, 'topic' => $topic,
                'title' => "Lesson $topic", 'vimeo_url' => 'https://player.vimeo.com/video/'.(100 + $index), 'is_active' => true]);
            $this->lessons[$topic] = $lesson;
            $path = "lesson-pdfs/$topic.pdf"; Storage::disk('local')->put($path, "%PDF-1.4 $topic"); $lesson->update(['question_pdf_path' => $path]);
            $source = LessonQuestionSource::where('lesson_id', $lesson->id)->sole(); $ids = [];
            for ($i = 0; $i < 5; $i++) {
                $id = hash('sha256', "$topic:$i"); $ids[] = $id;
                $bank[$id] = ['id' => $id, 'active' => true, 'grade_id' => $this->grade, 'subject_id' => $this->subject, 'text' => "Question $topic $i",
                    'origins' => [['source_key' => "{$this->grade}:{$this->subject}:{$lesson->id}:question", 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'page' => 1, 'label' => (string) $i]]];
                QuestionReview::create(['id' => $id, 'grade_id' => $this->grade, 'subject_id' => $this->subject, 'status' => 'approved', 'content' => ['text' => "Question $topic $i"], 'origins' => []]);
            }
            $source->update(['status' => 'processed', 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'question_ids' => $ids]);
        }
        Storage::disk('local')->put('structured/bank.json', json_encode(['questions' => $bank]));
    }

    protected function tearDown(): void { $this->closeLearningDatabase(); parent::tearDown(); }

    private function activate(?User $student = null): int
    {
        Sanctum::actingAs($this->tutor);
        return $this->postJson('/api/admin/students/'.($student ?? $this->student)->id.'/automatic-test-papers',
            ['grade_id' => $this->grade, 'subject_id' => $this->subject])->assertOk()->json('id');
    }

    private function complete(string $topic): void
    {
        $this->travel(2)->seconds(); Sanctum::actingAs($this->student);
        $lesson = $this->lessons[$topic];
        $this->putJson('/api/lesson-progress/'.$lesson->id.'/completion', ['video_url' => $lesson->vimeo_url])->assertOk();
    }

    private function reconcile(int $id): array { return app(AutomaticAssessmentService::class)->reconcile($id); }

    public function test_six_topics_create_two_disjoint_fifteen_question_papers_in_completion_order(): void
    {
        $id = $this->activate();
        foreach (['C', 'A'] as $topic) $this->complete($topic);
        $this->assertSame('waiting_topics', $this->reconcile($id)['status']);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 0);
        $this->complete('B'); $this->assertSame('issued', $this->reconcile($id)['status']);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('next.id', TestPaper::sole()->id);
        $this->assertSame(['C', 'A', 'B'], AutomaticAssessmentBatch::first()->topics);
        $this->assertSame(1, TestPaper::count());
        foreach (['F', 'D'] as $topic) $this->complete($topic);
        $this->reconcile($id); $this->assertSame(1, TestPaper::count());
        $this->complete('E'); $this->assertSame('issued', $this->reconcile($id)['status']);
        $papers = TestPaper::with('questions')->get();
        $this->assertCount(2, $papers);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 2);
        $this->assertCount(15, $papers[0]->questions); $this->assertCount(15, $papers[1]->questions);
        $this->assertSame([], array_intersect($papers[0]->questions->pluck('source_question_id')->all(), $papers[1]->questions->pluck('source_question_id')->all()));
        foreach (range(1, 3) as $ignored) $this->reconcile($id);
        $this->complete('A'); $this->reconcile($id);
        $this->assertSame(2, TestPaper::count()); $this->assertSame(6, StudentTopicCompletion::count());
    }

    public function test_existing_complete_topics_are_included_without_manual_activation(): void
    {
        foreach (['A', 'B', 'C'] as $topic) $this->complete($topic);
        $id = StudentAssessmentEnrolment::sole()->id;
        $this->assertSame($id, $this->activate());
        $this->artisan('assessments:reconcile')->assertSuccessful();
        $this->assertSame(['A', 'B', 'C'], AutomaticAssessmentBatch::sole()->topics);
        $this->assertSame(3, StudentTopicCompletion::count());
        foreach (['D', 'E', 'F'] as $topic) $this->complete($topic);
        $this->reconcile($id); $this->assertSame(['D', 'E', 'F'], AutomaticAssessmentBatch::orderByDesc('sequence')->first()->topics);
        $this->assertSame(2, TestPaper::count());
    }

    public function test_shortages_preserve_batch_and_retry_after_approval_without_reusing_topics(): void
    {
        $id = $this->activate(); QuestionReview::where('id', hash('sha256', 'A:0'))->update(['status' => 'pending']);
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $topic) $this->complete($topic);
        $this->assertSame('waiting_questions', $this->reconcile($id)['status']);
        $firstId = AutomaticAssessmentBatch::orderBy('sequence')->first()->id;
        // The waiting first batch must not starve a ready second batch.
        $this->assertSame('issued', $this->reconcile($id)['status']);
        $this->assertSame(['D', 'E', 'F'], TestPaper::first()->batch->topics);
        QuestionReview::where('id', hash('sha256', 'A:0'))->update(['status' => 'approved']);
        $this->assertSame('issued', $this->reconcile($id)['status']);
        $this->assertSame($firstId, AutomaticAssessmentBatch::orderBy('sequence')->first()->id);
        $this->assertSame(2, TestPaper::count());
    }

    public function test_all_active_primary_lessons_are_required_and_wrong_video_sources_do_not_count(): void
    {
        $extra = Lesson::create(['grade_id' => $this->grade, 'subject_id' => $this->subject, 'topic' => 'A', 'title' => 'Second A',
            'vimeo_url' => 'https://player.vimeo.com/video/999', 'is_active' => true]);
        $id = $this->activate(); $this->complete('A'); $this->reconcile($id); $this->assertSame(0, StudentTopicCompletion::count());
        LessonProgress::create(['user_id' => $this->student->id, 'lesson_id' => $extra->id, 'video_type' => 'answer', 'video_source' => 'wrong', 'completed_at' => now()]);
        $this->reconcile($id); $this->assertSame(0, StudentTopicCompletion::count());
        $record = LessonProgress::create(['user_id' => $this->student->id, 'lesson_id' => $extra->id, 'video_type' => 'lesson', 'video_source' => 'stale', 'completed_at' => now()]);
        $this->reconcile($id); $this->assertSame(0, StudentTopicCompletion::count());
        $record->update(['video_source' => app(LessonProgressService::class)->videoSource($extra->vimeo_url)]);
        $this->reconcile($id); $this->assertSame(1, StudentTopicCompletion::count());
    }

    public function test_event_and_paper_history_survives_curriculum_changes_and_renames_do_not_retest_old_lessons(): void
    {
        $id = $this->activate(); $this->complete('A'); $this->reconcile($id);
        $this->lessons['A']->update(['topic' => 'Renamed A']);
        $result = $this->reconcile($id); $this->assertSame(['Renamed A'], $result['renamed_topics_need_review']);
        $this->assertSame(1, StudentTopicCompletion::count());
        foreach (['B', 'C'] as $topic) $this->complete($topic);
        $this->assertSame('waiting_curriculum', $this->reconcile($id)['status']);
        $this->assertSame(0, TestPaper::count());
    }

    public function test_automatic_registration_keeps_staff_management_permissions(): void
    {
        $this->complete('A'); $this->assertSame(1, StudentAssessmentEnrolment::count());
        $this->postJson('/api/admin/students/'.$this->student->id.'/automatic-test-papers', ['grade_id' => $this->grade, 'subject_id' => $this->subject])->assertForbidden();
        $this->getJson('/api/admin/automatic-test-papers')->assertForbidden();
        $other = User::factory()->create(['role' => 'student']); $id = $this->activate();
        Sanctum::actingAs($this->tutor);
        $this->postJson('/api/admin/students/'.$other->id.'/automatic-test-papers', ['grade_id' => $this->grade, 'subject_id' => $this->subject])->assertForbidden();
        $this->assertSame(1, StudentAssessmentEnrolment::count());
        $this->assertSame($this->student->id, StudentAssessmentEnrolment::findOrFail($id)->student_id);
    }

    public function test_progress_writes_durable_outbox_and_dry_run_makes_no_changes(): void
    {
        $id = $this->activate(); $this->complete('A');
        $this->assertTrue(StudentAssessmentEnrolment::findOrFail($id)->needs_reconcile);
        $this->artisan('assessments:reconcile', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, StudentTopicCompletion::count());
        $this->artisan('assessments:reconcile', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(1, StudentTopicCompletion::count());
        $this->assertFalse(StudentAssessmentEnrolment::findOrFail($id)->needs_reconcile);
        $this->artisan('assessments:reconcile', ['--limit' => 0])->assertFailed();
    }

    public function test_pause_and_student_course_access_are_respected_without_staff_dependency(): void
    {
        $id = $this->activate(); foreach (['A', 'B', 'C'] as $topic) $this->complete($topic);
        Sanctum::actingAs($this->tutor);
        $this->putJson('/api/admin/automatic-test-papers/'.$id, ['enabled' => false])->assertOk();
        $this->assertSame('disabled', $this->reconcile($id)['status']);
        $this->putJson('/api/admin/automatic-test-papers/'.$id, ['enabled' => true])->assertOk();
        LessonAccess::query()->update(['expires_at' => now()->subDay()]);
        $this->assertSame('waiting_access', $this->reconcile($id)['status']);
        LessonAccess::query()->update(['expires_at' => null]); $this->tutor->update(['role' => 'student']);
        $this->assertSame('issued', $this->reconcile($id)['status']);
        $this->assertSame($this->student->id, TestPaper::sole()->created_by);
    }

    public function test_worker_discovers_preexisting_completions_and_dry_run_writes_nothing(): void
    {
        LessonProgress::withoutEvents(function () {
            foreach (['A', 'B', 'C'] as $topic) LessonProgress::create(['user_id' => $this->student->id,
                'lesson_id' => $this->lessons[$topic]->id, 'video_type' => 'lesson',
                'video_source' => app(LessonProgressService::class)->videoSource($this->lessons[$topic]->vimeo_url), 'completed_at' => now()->subDays(3)]);
        });
        $this->artisan('assessments:reconcile', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, StudentAssessmentEnrolment::count());
        $this->artisan('assessments:reconcile')->assertSuccessful();
        $this->assertSame(1, TestPaper::count());
        $this->artisan('assessments:reconcile')->assertSuccessful();
        $this->assertSame(1, TestPaper::count());
    }

    public function test_admin_and_tutor_own_progress_generates_own_papers_without_course_enrolment(): void
    {
        foreach (['admin', 'tutor'] as $role) {
            $this->student = User::factory()->create(['role' => $role]);
            foreach (['A', 'B', 'C'] as $topic) LessonProgress::create(['user_id' => $this->student->id,
                'lesson_id' => $this->lessons[$topic]->id, 'video_type' => 'lesson',
                'video_source' => app(LessonProgressService::class)->videoSource($this->lessons[$topic]->vimeo_url), 'completed_at' => now()]);
            $this->artisan('assessments:reconcile')->assertSuccessful();
            $paper = TestPaper::whereHas('batch', fn ($q) => $q->where('student_id', $this->student->id))->sole();
            Sanctum::actingAs($this->student);
            $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('next.id', $paper->id);
            $this->postJson('/api/test-papers/'.$paper->id.'/start')->assertOk();
        }
    }

    public function test_shared_question_from_an_earlier_main_paper_is_not_reused_in_new_topics(): void
    {
        $id = $this->activate();
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $shared = hash('sha256', 'A:0'); $removed = hash('sha256', 'D:0');
        $bank['questions'][$shared]['origins'][] = $bank['questions'][$removed]['origins'][0];
        $bank['questions'][$removed]['active'] = false;
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $source = LessonQuestionSource::where('lesson_id', $this->lessons['D']->id)->sole();
        $source->update(['question_ids' => array_values(array_map(fn ($qid) => $qid === $removed ? $shared : $qid, $source->question_ids))]);
        foreach (['A', 'B', 'C'] as $topic) $this->complete($topic);
        $this->assertSame('issued', $this->reconcile($id)['status']);
        foreach (['D', 'E', 'F'] as $topic) $this->complete($topic);
        $this->assertSame('waiting_questions', $this->reconcile($id)['status']);
        $this->assertSame(1, TestPaper::count());
    }
}
