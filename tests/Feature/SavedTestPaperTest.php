<?php

namespace Tests\Feature;

use App\Models\AssessmentBatch;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\Subject;
use App\Models\TestPaper;
use App\Models\TestPaperAttempt;
use App\Models\TestPaperQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class SavedTestPaperTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    private User $student;
    private User $other;
    private User $tutor;
    private array $input;
    private string $diagramHash;
    private string $diagramBytes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase();
        foreach (['2026_10_19_000001_create_lesson_question_sources_table.php', '2026_10_19_000002_create_question_reviews_table.php',
            '2026_11_01_000001_create_question_corrections_table.php', '2026_11_12_000001_create_saved_test_papers.php',
            '2026_11_12_000004_create_test_paper_responses.php', '2026_11_13_000001_add_handwriting_to_test_paper_answers.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Storage::fake('local');
        $this->student = User::factory()->create(['role' => 'student']);
        $this->other = User::factory()->create(['role' => 'student']);
        $this->tutor = User::factory()->create(['role' => 'tutor']);
        $grade = Grade::create(['name' => 'Grade 8'])->id;
        $subject = Subject::create(['name' => 'Mathematics'])->id;
        LessonAccess::create(['user_id' => $this->student->id, 'grade_id' => $grade, 'subject_id' => $subject, 'status' => 'accepted']);
        $this->input = ['student_id' => $this->student->id, 'grade_id' => $grade, 'subject_id' => $subject,
            'purpose' => 'main', 'batch_key' => (string) Str::uuid(), 'topics' => ['A', 'B', 'C']];
        $this->diagramBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5wAAAABJRU5ErkJggg==');
        $this->diagramHash = hash('sha256', $this->diagramBytes);
        Storage::disk('local')->put('structured/diagrams/'.$this->diagramHash.'.png', $this->diagramBytes);
        $bank = [];
        foreach ($this->input['topics'] as $topic) {
            $lesson = Lesson::create(['grade_id' => $grade, 'subject_id' => $subject, 'topic' => $topic,
                'title' => "Lesson $topic", 'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
            $path = "lesson-pdfs/$topic.pdf"; Storage::disk('local')->put($path, "%PDF-1.4 $topic");
            $lesson->update(['question_pdf_path' => $path]);
            $source = LessonQuestionSource::where('lesson_id', $lesson->id)->sole();
            $ids = [];
            for ($i = 0; $i < 5; $i++) {
                $id = hash('sha256', "$topic:$i"); $ids[] = $id;
                $content = ['text' => "Question $topic $i"];
                if ($topic === 'A' && $i === 0) $content['diagrams'] = [['sha256' => $this->diagramHash,
                    'path' => 'structured/diagrams/'.$this->diagramHash.'.png', 'width' => 1, 'height' => 1]];
                $bank[$id] = $content + ['id' => $id, 'active' => true, 'grade_id' => $grade, 'subject_id' => $subject,
                    'origins' => [['source_key' => "$grade:$subject:{$lesson->id}:question", 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'page' => 1, 'label' => (string) $i]]];
                QuestionReview::create(['id' => $id, 'grade_id' => $grade, 'subject_id' => $subject,
                    'status' => 'approved', 'content' => $content, 'origins' => [], 'approved_at' => now()]);
            }
            $source->update(['status' => 'processed', 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'question_ids' => $ids]);
        }
        Storage::disk('local')->put('structured/bank.json', json_encode(['questions' => $bank]));
    }

    protected function tearDown(): void { $this->closeLearningDatabase(); parent::tearDown(); }

    private function issue(): string
    {
        Sanctum::actingAs($this->tutor);
        return $this->postJson('/api/admin/test-papers', $this->input)->assertOk()->assertJsonPath('status', 'available')
            ->assertJsonPath('marking_ready', false)->assertJsonCount(15, 'questions')->json('id');
    }

    private function completeAnswers(array $answers): array
    {
        $byNumber = array_column($answers, null, 'number');
        return array_map(fn ($number) => $byNumber[$number] ?? [
            'number' => $number, 'answer' => '1', 'working' => '',
        ], range(1, 15));
    }

    public function test_attention_is_private_owned_and_tracks_start_and_submission(): void
    {
        $this->getJson('/api/test-papers/attention')->assertUnauthorized();
        $id = $this->issue();
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 0);
        Sanctum::actingAs($this->other);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertExactJson([
            'total' => 0, 'available_count' => 0, 'started_count' => 0, 'next' => null,
        ]);
        Sanctum::actingAs($this->student);
        $response = $this->getJson('/api/test-papers/attention?student_id='.$this->other->id)->assertOk()->assertExactJson([
            'total' => 1, 'available_count' => 1, 'started_count' => 0,
            'next' => ['id' => $id, 'status' => 'available', 'grade' => 'Grade 8', 'subject' => 'Mathematics'],
        ]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('started_count', 1)->assertJsonPath('next.status', 'started');
        $this->putJson("/api/test-papers/$id/answers", ['revision' => 0, 'answers' => $this->completeAnswers([['number' => 1, 'answer' => '4', 'working' => '2 + 2']])])->assertOk();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 1])->assertOk();
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('next', null);
    }

    public function test_attention_hides_unstartable_papers_but_keeps_in_progress_work(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        LessonAccess::query()->update(['expires_at' => now()->subDay()]);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 0);
        LessonAccess::query()->update(['expires_at' => null]);
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        LessonAccess::query()->update(['expires_at' => now()->subDay()]);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('next.id', $id);
        Sanctum::actingAs($this->tutor);
        $this->postJson("/api/admin/test-papers/$id/cancel", ['reason' => 'A corrected paper is needed'])->assertOk();
        Sanctum::actingAs($this->student);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_attention_gracefully_handles_uninstalled_paper_tables(): void
    {
        Schema::drop('test_paper_questions');
        Sanctum::actingAs($this->student);
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('next', null);
    }

    public function test_staff_can_answer_own_paper_but_cannot_edit_another_learners_work(): void
    {
        foreach (['admin', 'tutor'] as $role) {
            $learner = User::factory()->create(['role' => $role]);
            $this->input['student_id'] = $learner->id;
            $this->input['batch_key'] = (string) Str::uuid();
            $id = $this->issue();
            Sanctum::actingAs($learner);
            $this->getJson('/api/test-papers')->assertOk()->assertJsonCount(1, 'data');
            $this->postJson("/api/test-papers/$id/start")->assertOk()->assertJsonPath('answering_available', true);
            $this->putJson("/api/test-papers/$id/answers", ['revision' => 0, 'answers' => $this->completeAnswers([['number' => 1, 'answer' => '4', 'working' => '2 + 2']])])->assertOk();
            Sanctum::actingAs($this->tutor);
            $this->putJson("/api/test-papers/$id/answers", ['revision' => 1, 'answers' => [['number' => 1, 'answer' => '5', 'working' => '']]])->assertForbidden();
            Sanctum::actingAs($learner);
            $this->postJson("/api/test-papers/$id/submit", ['revision' => 1])->assertOk()->assertJsonPath('status', 'awaiting_review');
        }
    }

    public function test_oldest_unfinished_paper_is_next_even_when_a_newer_paper_is_started(): void
    {
        $first = $this->issue();
        $this->travel(1)->minute();
        $this->input['purpose'] = 'revision';
        $this->input['batch_key'] = (string) Str::uuid();
        $second = $this->issue();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$second/start")->assertOk();
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonPath('available_count', 1)->assertJsonPath('started_count', 1)->assertJsonPath('next.id', $first);
        $this->getJson('/api/test-papers?per_page=1')->assertOk()->assertJsonPath('data.0.id', $first)->assertJsonPath('last_page', 2);
        $this->getJson('/api/test-papers?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $second);
        $this->getJson('/api/test-papers?per_page=100')->assertUnprocessable();
        $this->postJson("/api/test-papers/$first/start")->assertOk();
        $this->putJson("/api/test-papers/$first/answers", ['revision' => 0, 'answers' => $this->completeAnswers([['number' => 1, 'answer' => '4', 'working' => '']])])->assertOk();
        $this->postJson("/api/test-papers/$first/submit", ['revision' => 1])->assertOk();
        $this->getJson('/api/test-papers/attention')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('next.id', $second);
        $this->getJson('/api/test-papers?per_page=1')->assertOk()->assertJsonPath('data.0.id', $second);
        $this->getJson('/api/test-papers?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $first)->assertJsonPath('data.0.status', 'awaiting_review');
        $this->assertNotSame($first, $second);
    }

    public function test_repeated_assignment_saves_one_batch_paper_attempt_and_fixed_questions(): void
    {
        $id = $this->issue();
        $first = $this->getJson("/api/test-papers/$id")->assertOk()->json('questions');
        foreach (array_chunk($first, 5) as $page) {
            $this->assertCount(3, array_unique(array_column($page, 'topic')));
        }
        $this->postJson('/api/admin/test-papers', $this->input)->assertOk()->assertJsonPath('id', $id)->assertJsonPath('questions', $first);
        $this->assertSame(1, AssessmentBatch::count());
        $this->assertSame(1, TestPaper::count());
        $this->assertSame(1, TestPaperAttempt::count());
        $this->assertSame(15, TestPaperQuestion::count());
        $this->assertSame(15, TestPaperQuestion::distinct()->count('source_question_id'));
        $this->assertSame(2, DB::table('test_paper_events')->count());
        $this->assertSame(1, DB::table('test_paper_assets')->count());
        $this->assertNull(TestPaperQuestion::first()->marks);
        $this->assertNull(TestPaperQuestion::first()->marking_snapshot);
        $this->assertNotEmpty(TestPaperQuestion::first()->provenance['origins'][0]['source_sha256']);

        $positions = array_column($first, 'number');
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        $number = $positions[0];
        $this->putJson("/api/test-papers/$id/answers", [
            'revision' => 0, 'answers' => [['number' => $number, 'answer' => '4', 'working' => '2 + 2']],
        ])->assertOk();
        $resumed = $this->getJson("/api/test-papers/$id")->assertOk();
        $this->assertSame($positions, array_column($resumed->json('questions'), 'number'));
        $resumed->assertJsonPath('responses.answers.0.number', $number)
            ->assertJsonPath('responses.answers.0.answer', '4')
            ->assertJsonPath('responses.answers.0.working', '2 + 2');
    }

    public function test_guests_students_and_unknown_roles_cannot_issue_papers(): void
    {
        $this->postJson('/api/admin/test-papers', $this->input)->assertUnauthorized();
        $this->getJson('/api/test-papers')->assertUnauthorized();
        Sanctum::actingAs($this->student);
        $this->postJson('/api/admin/test-papers', $this->input)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'unknown']));
        $this->postJson('/api/admin/test-papers', $this->input)->assertForbidden();
        $this->assertSame(0, TestPaper::count());
    }

    public function test_ownership_protects_questions_diagrams_lists_and_start(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->other);
        $this->getJson("/api/test-papers/$id")->assertNotFound();
        $this->getJson("/api/test-papers/$id/diagrams/{$this->diagramHash}")->assertNotFound();
        $this->postJson("/api/test-papers/$id/start", ['student_id' => $this->student->id])->assertNotFound();
        $this->getJson('/api/test-papers')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($this->student);
        $this->getJson('/api/test-papers?student_id='.$this->other->id)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/admin/test-papers/$id/cancel", ['reason' => 'Forged request'])->assertForbidden();
        $this->postJson("/api/admin/test-papers/$id/replace", ['request_key' => (string) Str::uuid(), 'reason' => 'Forged request'])->assertForbidden();
    }

    public function test_student_payload_is_allowlisted_and_has_no_answer_keys_or_topic_labels(): void
    {
        $id = $this->issue();
        $q = TestPaperQuestion::first();
        // Simulate future private marking metadata; it must not be serialised to students.
        DB::table('test_paper_questions')->where('id', $q->id)->update(['marking_snapshot' => json_encode(['answer_key' => 'SECRET']),
            'content' => json_encode($q->content + ['answer_key' => 'SECRET', 'rubric' => 'SECRET'])]);
        Sanctum::actingAs($this->student);
        $response = $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('answering_available', true);
        $this->assertStringNotContainsString('SECRET', $response->getContent());
        foreach (['topics', 'student_id', 'events', 'batch_key'] as $key) $this->assertArrayNotHasKey($key, $response->json());
        foreach ($response->json('questions') as $question) {
            $this->assertSame(['number', 'content'], array_keys($question));
            foreach ($question['content']['diagrams'] ?? [] as $diagram) $this->assertArrayNotHasKey('path', $diagram);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_source_replacement_and_deletion_do_not_change_saved_paper_or_diagrams(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $before = $this->getJson("/api/test-papers/$id")->assertOk()->json();
        Storage::disk('local')->delete(['structured/bank.json', 'lesson-pdfs/A.pdf', 'structured/diagrams/'.$this->diagramHash.'.png']);
        QuestionReview::query()->update(['status' => 'pending']);
        $this->getJson("/api/test-papers/$id")->assertOk()->assertExactJson($before);
        $diagram = $this->get("/api/test-papers/$id/diagrams/{$this->diagramHash}")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($this->diagramBytes, $diagram->getContent());
        $this->getJson("/api/test-papers/$id/diagrams/".str_repeat('f', 64))->assertNotFound();
    }

    public function test_start_is_idempotent_and_never_records_answers_or_marks(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start", ['score' => 100, 'answers' => ['fake']])->assertOk()->assertJsonPath('status', 'started');
        $started = TestPaperAttempt::sole()->started_at->toISOString();
        $this->travel(5)->minutes();
        $this->postJson("/api/test-papers/$id/start")->assertOk()->assertJsonPath('attempt.started_at', $started);
        $this->assertNull(TestPaperAttempt::sole()->score);
        $this->assertNull(TestPaperAttempt::sole()->submitted_at);
        $this->assertSame(3, DB::table('test_paper_events')->count());
    }

    public function test_course_access_is_required_for_creation_and_start_but_history_is_retained(): void
    {
        $id = $this->issue();
        LessonAccess::query()->update(['expires_at' => now()->subDay()]);
        Sanctum::actingAs($this->student);
        $this->getJson("/api/test-papers/$id")->assertOk();
        $this->postJson("/api/test-papers/$id/start")->assertForbidden();
        Sanctum::actingAs($this->tutor);
        $input = $this->input; $input['batch_key'] = (string) Str::uuid();
        $this->postJson('/api/admin/test-papers', $input)->assertForbidden();
        $this->assertSame(1, AssessmentBatch::count());
    }

    public function test_batch_key_conflict_does_not_change_original_scope(): void
    {
        $id = $this->issue();
        $input = $this->input; $input['topics'] = ['A', 'B', 'Other'];
        $this->postJson('/api/admin/test-papers', $input)->assertConflict();
        $this->assertSame(['A', 'B', 'C'], TestPaper::findOrFail($id)->batch->topics);
    }

    public function test_cancellation_and_replacement_preserve_original_and_are_idempotent(): void
    {
        $id = $this->issue();
        $oldQuestions = TestPaper::findOrFail($id)->questions->toJson();
        $request = ['request_key' => (string) Str::uuid(), 'reason' => 'Tutor requested a replacement'];
        $new = $this->postJson("/api/admin/test-papers/$id/replace", $request)->assertOk()->assertJsonPath('version', 2)
            ->assertJsonPath('previous_paper_id', $id)->json('id');
        $this->postJson("/api/admin/test-papers/$id/replace", $request)->assertOk()->assertJsonPath('id', $new);
        $this->postJson('/api/admin/test-papers', $this->input)->assertOk()->assertJsonPath('id', $id)->assertJsonPath('status', 'cancelled');
        $this->assertSame($oldQuestions, TestPaper::findOrFail($id)->questions->toJson());
        $this->assertSame(2, TestPaperAttempt::count());
        $this->assertSame(30, TestPaperQuestion::count());
        $this->postJson("/api/admin/test-papers/$id/replace", ['request_key' => (string) Str::uuid(), 'reason' => 'Duplicate replacement'])->assertConflict();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertConflict();
    }

    public function test_failed_replacement_rolls_back_cancellation_and_new_records(): void
    {
        $id = $this->issue();
        Storage::disk('local')->put('lesson-pdfs/A.pdf', 'changed');
        $this->postJson("/api/admin/test-papers/$id/replace", ['request_key' => (string) Str::uuid(), 'reason' => 'Replace please'])->assertUnprocessable();
        $this->assertSame('available', TestPaper::findOrFail($id)->status);
        $this->assertSame(1, TestPaper::count());
        $this->assertSame(2, DB::table('test_paper_events')->count());
    }

    public function test_shortages_roll_back_batch_and_do_not_save_partial_papers(): void
    {
        QuestionReview::where('id', hash('sha256', 'A:0'))->update(['status' => 'pending']);
        Sanctum::actingAs($this->tutor);
        $this->postJson('/api/admin/test-papers', $this->input)->assertUnprocessable();
        foreach (['assessment_batches', 'test_papers', 'test_paper_questions', 'test_paper_assets', 'test_paper_attempts', 'test_paper_events'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_only_live_work_can_be_cancelled_and_reason_is_required(): void
    {
        $id = $this->issue();
        $this->postJson("/api/admin/test-papers/$id/cancel", [])->assertUnprocessable();
        DB::table('test_papers')->where('id', $id)->update(['status' => 'submitted']);
        $this->postJson("/api/admin/test-papers/$id/cancel", ['reason' => 'Too late'])->assertConflict();
        DB::table('test_papers')->where('id', $id)->update(['status' => 'available']);
        $this->postJson("/api/admin/test-papers/$id/cancel", ['reason' => 'Issued in error'])->assertOk()->assertJsonPath('status', 'cancelled');
        $events = DB::table('test_paper_events')->count();
        $this->postJson("/api/admin/test-papers/$id/cancel", ['reason' => 'Issued in error'])->assertOk();
        $this->assertSame($events, DB::table('test_paper_events')->count());
    }

    public function test_question_snapshots_cannot_be_edited_through_models(): void
    {
        $this->issue();
        $this->expectException(\LogicException::class);
        TestPaperQuestion::first()->update(['content' => ['text' => 'Overwrite']]);
    }

    public function test_purposes_are_separate_and_invalid_input_is_rejected(): void
    {
        $this->issue();
        $input = $this->input; $input['purpose'] = 'revision';
        $this->postJson('/api/admin/test-papers', $input)->assertOk()->assertJsonPath('purpose', 'revision');
        $input['purpose'] = 'follow_up';
        $this->postJson('/api/admin/test-papers', $input)->assertOk()->assertJsonPath('purpose', 'follow_up');
        $input['purpose'] = 'fake';
        $this->postJson('/api/admin/test-papers', $input)->assertUnprocessable();
        $input = $this->input; $input['student_id'] = User::factory()->create(['role' => 'unknown'])->id;
        $this->postJson('/api/admin/test-papers', $input)->assertUnprocessable();
        $input = $this->input; $input['topics'] = ['A', 'A', 'C'];
        $this->postJson('/api/admin/test-papers', $input)->assertUnprocessable();
    }

    public function test_schema_has_database_uniqueness_and_missing_migration_returns_503(): void
    {
        $this->issue();
        $batch = AssessmentBatch::sole()->getAttributes(); $batch['id'] = (string) Str::uuid();
        try { DB::table('assessment_batches')->insert($batch); $this->fail('Duplicate batch was accepted'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('UNIQUE', strtoupper($e->getMessage())); }
        Schema::drop('test_paper_events');
        Sanctum::actingAs($this->student);
        $this->getJson('/api/test-papers')->assertStatus(503);
    }

    public function test_working_and_final_answer_save_resume_and_submit_without_calculating_a_score(): void
    {
        $id = $this->issue(); Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        $formattedAnswer = '\(\frac34+2^3\)';
        $formattedWorking = '\(\begin{gathered}\frac{\placeholder{}}4\\ 2^3\end{gathered}\)';
        $body = ['revision' => 0, 'answers' => $this->completeAnswers([
            ['number' => 1, 'answer' => '5/4', 'working' => "3/4 + 2/4\n= 5/4"],
            ['number' => 2, 'answer' => $formattedAnswer, 'working' => $formattedWorking],
        ])];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk()->assertJsonPath('revision', 1);
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.revision', 1)
            ->assertJsonPath('responses.answers.0.answer', '5/4')->assertJsonPath('responses.answers.0.working', "3/4 + 2/4\n= 5/4")
            ->assertJsonPath('responses.answers.1.answer', $formattedAnswer)->assertJsonPath('responses.answers.1.working', $formattedWorking);
        $this->travel(10)->seconds();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 1, 'score' => 100])->assertOk()
            ->assertJsonPath('status', 'awaiting_review')->assertJsonPath('answering_available', false);
        $submitted = TestPaperAttempt::sole()->submitted_at->toISOString();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 1])->assertOk()->assertJsonPath('attempt.submitted_at', $submitted);
        $this->assertNull(TestPaperAttempt::sole()->score); $this->assertNull(TestPaperAttempt::sole()->marked_at);
        $this->assertSame(10, TestPaperAttempt::sole()->duration_seconds);
        $this->assertSame(5, DB::table('test_paper_events')->count());
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('stats.tests_attempted', 1)->assertJsonPath('stats.average_score', null);
        Sanctum::actingAs($this->tutor);
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.answers.0.answer', '5/4');
    }

    public function test_stale_tabs_and_late_autosaves_cannot_overwrite_saved_or_submitted_answers(): void
    {
        $id = $this->issue(); Sanctum::actingAs($this->student); $this->postJson("/api/test-papers/$id/start")->assertOk();
        $body = ['revision' => 0, 'answers' => $this->completeAnswers([['number' => 1, 'answer' => '8', 'working' => '2^3']])];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk();
        $body['answers'][0]['answer'] = 'wrong stale answer';
        $this->putJson("/api/test-papers/$id/answers", $body)->assertConflict();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 0])->assertConflict();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 1])->assertOk();
        $body['revision'] = 1; $this->putJson("/api/test-papers/$id/answers", $body)->assertConflict();
        $this->assertSame('8', DB::table('test_paper_answers')->value('answer'));
    }

    public function test_answer_endpoints_restrict_ownership_and_require_a_started_paper(): void
    {
        $id = $this->issue(); $body = ['revision' => 0, 'answers' => [['number' => 1, 'answer' => '7', 'working' => '']]];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertForbidden();
        Sanctum::actingAs($this->other);
        $this->putJson("/api/test-papers/$id/answers", $body)->assertNotFound();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 0])->assertNotFound();
        Sanctum::actingAs($this->student);
        $this->putJson("/api/test-papers/$id/answers", $body)->assertConflict();
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 0])->assertUnprocessable();
        $this->assertSame(0, DB::table('test_paper_answers')->count());
    }

    public function test_submit_lists_blank_final_answers_in_display_order_and_keeps_paper_open(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $questions = $this->postJson("/api/test-papers/$id/start")->assertOk()->json('questions');
        $answers = $this->completeAnswers([
            ['number' => $questions[2]['number'], 'answer' => " \t ", 'working' => '2 + 2'],
            ['number' => $questions[6]['number'], 'answer' => '0', 'working' => ''],
        ]);
        $answers = array_values(array_filter($answers, fn ($answer) => $answer['number'] !== $questions[11]['number']));
        $this->putJson("/api/test-papers/$id/answers", ['revision' => 0, 'answers' => $answers])->assertOk();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 1])->assertUnprocessable()
            ->assertJsonPath('errors.answers.0', 'Final answers missing for questions 3, 12.');
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('status', 'started')->assertJsonPath('responses.revision', 1);
        $this->assertNull(TestPaperAttempt::sole()->submitted_at);
    }

    public function test_handwriting_is_preserved_but_submission_requires_final_answers(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertOk()->assertJsonPath('handwriting_available', true);
        $ink = ['v' => 1, 'strokes' => [[[0.1, 0.2], [0.3, 0.4]], [[0.5, 0.5]]]];
        $body = ['revision' => 0, 'answers' => [['number' => 1, 'answer' => '', 'working' => '', 'working_ink' => $ink]]];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk();
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.answers.0.working_ink', $ink)
            ->assertJsonPath('responses.answers.0.working_ink', $ink);
        Sanctum::actingAs($this->other);
        $this->getJson("/api/test-papers/$id")->assertNotFound();
        $this->putJson("/api/test-papers/$id/answers", $body)->assertNotFound();
        Sanctum::actingAs($this->student);
        $this->putJson("/api/test-papers/$id/answers", ['revision' => 1, 'answers' => [['number' => 1, 'answer' => '', 'working' => '']]])->assertOk();
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.answers.0.working_ink', $ink);
        $this->putJson("/api/test-papers/$id/answers", $body)->assertConflict();
        $this->putJson("/api/test-papers/$id/answers", ['revision' => 2, 'answers' => [['number' => 1, 'answer' => '', 'working' => '',
            'answer_ink' => null, 'working_ink' => ['v' => 1, 'strokes' => []]]]])->assertOk();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 3])->assertUnprocessable();
        $body['revision'] = 3;
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 4])->assertUnprocessable()->assertJsonValidationErrors('answers');
        $this->putJson("/api/test-papers/$id/answers", ['revision' => 4, 'answers' => $this->completeAnswers([])])->assertOk();
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 5])->assertOk()->assertJsonPath('status', 'awaiting_review');
        $this->putJson("/api/test-papers/$id/answers", array_replace($body, ['revision' => 5]))->assertConflict();
        Sanctum::actingAs($this->tutor);
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.answers.0.working_ink', $ink)
            ->assertJsonPath('handwriting_available', false);
        $this->assertNull(TestPaperAttempt::sole()->score);
    }

    public function test_final_answers_reject_new_handwriting_and_legacy_ink_is_not_an_entered_response(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        $ink = ['v' => 1, 'strokes' => [[[0.1, 0.2], [0.2, 0.3]]]];
        $body = ['revision' => 0, 'answers' => [['number' => 1, 'answer' => '', 'working' => '', 'answer_ink' => $ink]]];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertUnprocessable();
        $this->assertSame(0, DB::table('test_paper_answers')->count());
        unset($body['answers'][0]['answer_ink']);
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk();
        // Emulate a response saved before final-answer handwriting was disabled.
        DB::table('test_paper_answers')->update(['answer_ink' => json_encode($ink)]);
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 1])->assertUnprocessable();
        $body['revision'] = 1;
        $body['answers'][0]['answer'] = '\(7\)';
        $body['answers'] = $this->completeAnswers($body['answers']);
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk();
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.answers.0.answer', '\(7\)')
            ->assertJsonPath('responses.answers.0.answer_ink', $ink);
        $this->postJson("/api/test-papers/$id/submit", ['revision' => 2])->assertOk();
    }

    public function test_handwriting_rejects_images_invalid_coordinates_and_excessive_strokes_or_points(): void
    {
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertOk();
        $invalid = [
            'data:image/png;base64,picture', ['v' => 2, 'strokes' => []],
            ['v' => 1, 'strokes' => [[[1.01, 0.2]]]], ['v' => 1, 'strokes' => [[['0.1', 0.2]]]],
            ['v' => 1, 'strokes' => [[['x' => 0.1, 'y' => 0.2]]]], ['v' => 1, 'strokes' => [[]]],
            ['v' => 1, 'strokes' => array_fill(0, 201, [[0.1, 0.2]])],
            ['v' => 1, 'strokes' => [array_fill(0, 501, [0.1, 0.2])]],
            ['v' => 1, 'strokes' => array_fill(0, 200, array_fill(0, 26, [0.1, 0.2]))],
            ['v' => 1, 'strokes' => [], 'svg' => '<svg/>'],
        ];
        foreach ($invalid as $ink) {
            $this->putJson("/api/test-papers/$id/answers", ['revision' => 0,
                'answers' => [['number' => 1, 'answer' => '', 'working' => '', 'working_ink' => $ink]]])->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('test_paper_answers')->count());
    }

    public function test_missing_handwriting_migration_keeps_text_entry_and_returns_a_setup_error_for_ink(): void
    {
        // Only this test's private in-memory schema is changed.
        Schema::table('test_paper_answers', fn ($table) => $table->dropColumn(['answer_ink', 'working_ink']));
        $id = $this->issue();
        Sanctum::actingAs($this->student);
        $this->postJson("/api/test-papers/$id/start")->assertOk()->assertJsonPath('handwriting_available', false);
        $body = ['revision' => 0, 'answers' => [['number' => 1, 'answer' => '7', 'working' => '']]];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertOk();
        $body['revision'] = 1;
        $body['answers'][0]['working_ink'] = ['v' => 1, 'strokes' => [[[0.1, 0.2]]]];
        $this->putJson("/api/test-papers/$id/answers", $body)->assertStatus(503);
        $this->getJson("/api/test-papers/$id")->assertOk()->assertJsonPath('responses.answers.0.answer', '7')
            ->assertJsonPath('responses.answers.0.answer_ink', null);
    }

    public function test_invalid_question_numbers_oversized_answers_and_photo_fields_are_rejected(): void
    {
        $id = $this->issue(); Sanctum::actingAs($this->student); $this->postJson("/api/test-papers/$id/start")->assertOk();
        foreach ([['number' => 16, 'answer' => '7', 'working' => ''], ['number' => 1, 'answer' => str_repeat('a', 1001), 'working' => ''],
            ['number' => 1, 'answer' => '7', 'working' => '', 'photo' => 'not-supported']] as $answer) {
            $this->putJson("/api/test-papers/$id/answers", ['revision' => 0, 'answers' => [$answer]])->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('test_paper_answers')->count());
        $this->assertSame(0, DB::table('test_paper_response_versions')->count());
    }
}
