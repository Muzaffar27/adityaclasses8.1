<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\Subject;
use App\Models\User;
use App\Services\QuestionCorrectionService;
use App\Services\QuestionReviewService;
use App\Services\TestPaperReadinessService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class TestPaperReadinessTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    private int $grade;
    private int $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase();
        foreach (['2026_10_19_000001_create_lesson_question_sources_table.php', '2026_10_19_000002_create_question_reviews_table.php',
            '2026_11_01_000001_create_question_corrections_table.php'] as $migration) (require database_path('migrations/'.$migration))->up();
        Storage::fake('local');
        $this->grade = Grade::create(['name' => 'Grade 8'])->id;
        $this->subject = Subject::create(['name' => 'Mathematics'])->id;
        $bank = [];
        foreach (['A', 'B', 'C'] as $topic) {
            $lesson = Lesson::create(['grade_id' => $this->grade, 'subject_id' => $this->subject,
                'topic' => $topic, 'title' => "Lesson $topic", 'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
            $path = "lesson-pdfs/$topic.pdf"; Storage::disk('local')->put($path, "%PDF-1.4 $topic");
            $lesson->update(['question_pdf_path' => $path]);
            $source = LessonQuestionSource::where('lesson_id', $lesson->id)->sole();
            $ids = [];
            for ($i = 0; $i < 5; $i++) {
                $id = hash('sha256', "$topic:$i"); $ids[] = $id;
                $bank[$id] = ['id' => $id, 'active' => true, 'grade_id' => $this->grade, 'subject_id' => $this->subject,
                    'text' => "Question $topic $i", 'origins' => [['source_key' => "{$this->grade}:{$this->subject}:{$lesson->id}:question",
                        'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'page' => 1, 'label' => (string) $i]]];
                QuestionReview::create(['id' => $id, 'grade_id' => $this->grade, 'subject_id' => $this->subject,
                    'status' => 'approved', 'content' => ['text' => "Question $topic $i"], 'origins' => []]);
            }
            $source->update(['status' => 'processed', 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'question_ids' => $ids]);
        }
        Storage::disk('local')->put('structured/bank.json', json_encode(['questions' => $bank]));
    }

    protected function tearDown(): void
    {
        $this->closeLearningDatabase(); parent::tearDown();
    }

    public function test_readiness_and_preview_are_read_only_and_separate_marking_readiness(): void
    {
        $before = QuestionReview::orderBy('id')->get()->toJson();
        $sourcesBefore = LessonQuestionSource::orderBy('id')->get()->toJson();
        $bank = Storage::disk('local')->get('structured/bank.json');
        $service = app(TestPaperReadinessService::class);
        $report = $service->snapshot()['report'];
        $this->assertSame(15, $report['current_approved_unique']);
        $this->assertSame(0, $report['historical_or_unusable_approvals']);
        $this->assertSame(5, $report['topics'][0]['lessons'][0]['approved_count']);
        $this->assertSame(5, $report['topics'][0]['missing_metadata']['answer_key']);
        $this->assertFalse($report['marking_ready']);
        $paper = $service->preview($this->grade, $this->subject, ['A', 'B', 'C']);
        $this->assertTrue($paper['ready']);
        $this->assertCount(15, $paper['questions']);
        $this->assertFalse($paper['saved_attempt']);
        $this->assertSame($before, QuestionReview::orderBy('id')->get()->toJson());
        $this->assertSame($bank, Storage::disk('local')->get('structured/bank.json'));
        $this->assertSame($sourcesBefore, LessonQuestionSource::orderBy('id')->get()->toJson());
        $this->artisan('assessments:bank-readiness')->expectsOutputToContain('Current usable approvals: 15')->assertSuccessful();
        $this->artisan('assessments:preview', ['--grade' => $this->grade, '--subject' => $this->subject,
            '--topic' => ['A', 'B', 'C']])->assertSuccessful();
    }

    public function test_changed_pdf_and_pending_reviews_cannot_enter_preview(): void
    {
        Storage::disk('local')->put('lesson-pdfs/A.pdf', 'changed');
        QuestionReview::where('id', hash('sha256', 'B:0'))->update(['status' => 'pending']);
        $service = app(TestPaperReadinessService::class);
        $report = $service->snapshot()['report'];
        $this->assertSame(9, $report['current_approved_unique']);
        $this->assertSame(5, $report['historical_or_unusable_approvals']);
        $paper = $service->preview($this->grade, $this->subject, ['A', 'B', 'C']);
        $this->assertFalse($paper['ready']);
        $this->assertSame(3, $paper['topics'][0]['minimum_shortage']);
        $this->artisan('assessments:preview', ['--grade' => $this->grade, '--subject' => $this->subject,
            '--topic' => ['A', 'B', 'C']])->assertFailed();
    }

    public function test_missing_diagrams_and_mismatched_approval_course_are_unusable(): void
    {
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $id = hash('sha256', 'A:0');
        $bank['questions'][$id]['diagrams'] = [['sha256' => str_repeat('a', 64), 'path' => 'structured/diagrams/'.str_repeat('a', 64).'.png']];
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $foreign = Grade::create(['name' => 'Other grade']);
        QuestionReview::where('id', hash('sha256', 'B:0'))->update(['grade_id' => $foreign->id]);
        $report = app(TestPaperReadinessService::class)->snapshot()['report'];
        $this->assertSame(13, $report['current_approved_unique']);
        $this->assertSame(2, $report['historical_or_unusable_approvals']);
    }

    public function test_invalid_command_arguments_are_rejected(): void
    {
        $this->artisan('assessments:preview', ['--grade' => 'invalid', '--subject' => $this->subject])->assertFailed();
        $this->artisan('assessments:preview', ['--grade' => $this->grade, '--subject' => $this->subject,
            '--topic' => ['A', 'A', 'C']])->assertFailed();
    }

    public function test_only_approved_corrected_replacement_can_replace_the_original(): void
    {
        $reviews = app(QuestionReviewService::class);
        $reviews->synchronize();
        $id = hash('sha256', 'A:0');
        QuestionReview::whereKey($id)->update(['status' => 'pending']);
        $review = QuestionReview::findOrFail($id);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $corrections = app(QuestionCorrectionService::class);
        $flagged = $corrections->flag($id, $review->revision, $tutor->id, 'Wrong text');
        $replacement = $corrections->correct($id, $flagged->revision, ['text' => 'Corrected question'], $tutor->id);
        $service = app(TestPaperReadinessService::class);
        $this->assertSame(14, $service->snapshot()['report']['current_approved_unique']);
        $reviews->decide($replacement->id, true, $replacement->revision, $tutor->id, null);
        $paper = $service->preview($this->grade, $this->subject, ['A', 'B', 'C']);
        $this->assertTrue($paper['ready']);
        $this->assertContains($replacement->id, array_column($paper['questions'], 'id'));
        $this->assertNotContains($id, array_column($paper['questions'], 'id'));
    }

    public function test_empty_active_topics_are_reported_and_missing_foundations_fail_clearly(): void
    {
        Lesson::create(['grade_id' => $this->grade, 'subject_id' => $this->subject, 'topic' => 'D',
            'title' => 'No questions yet', 'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
        $report = app(TestPaperReadinessService::class)->snapshot()['report'];
        $empty = collect($report['topics'])->firstWhere('topic', 'D');
        $this->assertSame(0, $empty['approved_count']);
        $this->assertSame(3, $empty['needed_for_minimum']);
        \Illuminate\Support\Facades\Schema::drop('question_reviews');
        $this->artisan('assessments:bank-readiness')->expectsOutputToContain('Required table question_reviews is unavailable')->assertFailed();
    }
}
