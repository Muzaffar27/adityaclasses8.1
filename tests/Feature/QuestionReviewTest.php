<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\User;
use App\Services\QuestionReviewService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class QuestionReviewTest extends TestCase
{
    use UsesIsolatedLearningDatabase;
    private string $fingerprint;
    private LessonQuestionSource $source;
    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase();
        (require database_path('migrations/2026_10_19_000001_create_lesson_question_sources_table.php'))->up();
        (require database_path('migrations/2026_10_19_000002_create_question_reviews_table.php'))->up();
        Storage::fake('local');
        $this->fingerprint = str_repeat('a', 64);
        $this->lesson = Lesson::create(['grade_id' => Grade::create(['name' => 'Grade 8'])->id, 'subject_id' => Subject::create(['name' => 'Maths'])->id,
            'topic' => 'Fractions', 'title' => 'Fraction addition', 'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
        Storage::disk('local')->put('lesson-pdfs/source.pdf', "%PDF-1.4\nsource\n%%EOF");
        $this->lesson->update(['question_pdf_path' => 'lesson-pdfs/source.pdf']);
        $this->source = LessonQuestionSource::sole();
        $this->processed([$this->fingerprint]);
        $this->bank($this->fingerprint);
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
    }

    protected function tearDown(): void { $this->closeLearningDatabase(); parent::tearDown(); }

    private function processed(array $ids): void
    {
        $this->source->refresh()->update(['status' => 'processed', 'force_reprocess' => false, 'source_sha256' => hash_file('sha256', Storage::disk('local')->path($this->source->pdf_path)),
            'question_ids' => $ids, 'question_count' => count($ids), 'processed_at' => now()]);
    }

    private function bank(string $id): void
    {
        $s = $this->source->fresh();
        Storage::disk('local')->put('structured/bank.json', json_encode(['questions' => [$id => [
            'id' => $id, 'active' => true, 'grade_id' => $this->lesson->grade_id, 'subject_id' => $this->lesson->subject_id,
            'instructions' => 'Simplify:', 'math' => ['type' => 'number', 'value' => $id === $this->fingerprint ? '2' : '3'],
            'origins' => [['source_key' => "{$s->grade_id}:{$s->subject_id}:{$s->lesson_id}:question", 'source_sha256' => $s->source_sha256, 'page' => 1, 'label' => '1.']],
        ]]], JSON_THROW_ON_ERROR));
    }

    private function queue(): void { $this->getJson('/api/admin/question-reviews')->assertOk(); }
    private function decision(bool $yes, int $revision = 1) { return $this->putJson('/api/admin/question-reviews/' . $this->fingerprint, ['approved' => $yes, 'revision' => $revision, 'note' => 'Check denominator']); }

    public function test_yes_approves_once_and_only_approved_current_questions_are_eligible(): void
    {
        $service = app(QuestionReviewService::class);
        $this->queue();
        $this->assertSame([], $service->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        $this->decision(true)->assertOk()->assertJsonPath('status', 'approved');
        $review = QuestionReview::sole();
        $this->assertNotNull($review->approved_at);
        $this->decision(true)->assertOk()->assertJsonPath('revision', $review->revision);
        $this->assertSame(1, QuestionReview::count());
        $this->assertCount(1, $service->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        $this->getJson('/api/admin/question-reviews')->assertJsonCount(0, 'data')->assertJsonPath('counts.approved', 1);
    }

    public function test_no_queues_forced_processing_and_unchanged_results_require_review_again(): void
    {
        $this->queue();
        $this->decision(false)->assertOk()->assertJsonPath('status', 'reprocessing');
        $this->assertTrue($this->source->fresh()->force_reprocess);
        $this->assertSame('pending', $this->source->fresh()->status);
        $this->assertNull($this->source->fresh()->question_ids);
        $this->assertSame('Check denominator', QuestionReview::sole()->review_note);
        $this->processed([$this->fingerprint]);
        app(QuestionReviewService::class)->synchronize();
        $review = QuestionReview::sole();
        $this->assertSame('review_again', $review->status);
        $this->assertSame('unchanged', $review->retry_outcome);
        $this->assertNull($review->approved_at);
        $this->decision(true, $review->revision)->assertOk()->assertJsonPath('status', 'approved');
    }

    public function test_corrected_content_gets_a_new_pending_review_without_inheriting_approval(): void
    {
        $this->queue(); $this->decision(false)->assertOk();
        $newId = str_repeat('b', 64);
        $this->processed([$newId]); $this->bank($newId);
        app(QuestionReviewService::class)->synchronize();
        $this->assertSame('needs_attention', QuestionReview::find($this->fingerprint)->status);
        $this->assertSame('pending', QuestionReview::find($newId)->status);
        $this->assertNull(QuestionReview::find($newId)->approved_at);
        $this->decision(true, 3)->assertStatus(409);
    }

    public function test_unchanged_approved_content_survives_rescan_but_invalid_sources_are_ineligible(): void
    {
        $this->queue(); $this->decision(true)->assertOk();
        $this->source->update(['status' => 'pending', 'revision' => 2]);
        $service = app(QuestionReviewService::class);
        $this->assertSame([], $service->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        $this->processed([$this->fingerprint]); $service->synchronize();
        $this->assertSame('approved', QuestionReview::sole()->status);
        $this->assertCount(1, $service->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        Storage::disk('local')->put($this->source->pdf_path, 'different bytes');
        $this->assertSame([], $service->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
    }

    public function test_students_cannot_view_or_approve_and_stale_reviews_cannot_change_state(): void
    {
        $this->queue();
        $this->decision(false, 99)->assertStatus(409);
        $this->assertSame('pending', QuestionReview::sole()->status);
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/question-reviews')->assertForbidden();
        $this->decision(true)->assertForbidden();
        $this->assertNull(QuestionReview::sole()->approved_at);
    }

    public function test_duplicate_no_does_not_queue_another_attempt_and_failures_do_not_auto_approve(): void
    {
        $this->queue(); $this->decision(false)->assertOk();
        $revision = $this->source->fresh()->revision;
        $this->decision(false)->assertStatus(409);
        $this->assertSame($revision, $this->source->fresh()->revision);
        $this->source->update(['status' => 'failed']);
        app(QuestionReviewService::class)->synchronize();
        $this->assertSame('reprocessing', QuestionReview::sole()->status);
        $this->assertNull(QuestionReview::sole()->approved_at);
    }
}
