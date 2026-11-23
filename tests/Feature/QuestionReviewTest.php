<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\QuestionCorrection;
use App\Services\QuestionCorrectionService;
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
        (require database_path('migrations/2026_11_01_000001_create_question_corrections_table.php'))->up();
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

    public function test_sidebar_counts_separate_optional_approvals_from_corrections_until_resolved(): void
    {
        $url = '/api/admin/question-reviews';
        $this->getJson($url)->assertOk()->assertJsonPath('notification_counts', ['important' => 0, 'optional' => 1]);
        $this->flagQuestion();
        $this->getJson($url)->assertOk()->assertJsonPath('notification_counts', ['important' => 1, 'optional' => 0]);
        $saved = app(QuestionCorrectionService::class)->correct($this->fingerprint, 2, ['text' => 'Corrected question'], auth()->id());
        // Its pending replacement still belongs to the red correction, not both badges.
        $this->getJson($url)->assertOk()->assertJsonPath('notification_counts', ['important' => 1, 'optional' => 0]);
        $this->putJson('/api/admin/question-reviews/'.$saved->id, ['approved' => true, 'revision' => $saved->revision])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('notification_counts', ['important' => 0, 'optional' => 0]);
    }

    public function test_sidebar_counts_are_global_and_do_not_use_the_starter_shortlist(): void
    {
        $this->pdfBatch(10);
        $this->getJson('/api/admin/question-reviews?review_scope=starter')->assertOk()
            ->assertJsonPath('counts.pending', 5)->assertJsonPath('notification_counts', ['important' => 0, 'optional' => 10]);
        $this->getJson('/api/admin/question-reviews?grade_id=999&topic=Other')->assertOk()
            ->assertJsonPath('total', 0)->assertJsonPath('notification_counts', ['important' => 0, 'optional' => 10]);
    }

    public function test_sidebar_needs_attention_is_red_instead_of_optional_yellow(): void
    {
        $this->queue();
        QuestionReview::findOrFail($this->fingerprint)->update(['status' => 'needs_attention']);
        $this->getJson('/api/admin/question-reviews')->assertOk()
            ->assertJsonPath('notification_counts', ['important' => 1, 'optional' => 0]);
    }

    public function test_topic_picker_filters_both_review_modes_and_reports_progress(): void
    {
        $this->getJson('/api/admin/question-reviews?topic=Fractions')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('selection.goal_total', 5)
            ->assertJsonPath('selection.approved_for_goal', 0)->assertJsonPath('selection.topics.0.topic', 'Fractions');
        $this->getJson('/api/admin/question-reviews?topic=Other')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/question-reviews/pdfs?topic=Fractions')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/question-reviews/pdfs?topic=Other')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
    }

    public function test_starter_scope_limits_work_and_counts_approved_questions_toward_the_target(): void
    {
        $this->pdfBatch(20);
        $starter = $this->getJson('/api/admin/question-reviews?review_scope=starter')->assertOk()
            ->assertJsonPath('total', 5)->assertJsonPath('counts.pending', 5)->assertJsonPath('counts.library_pending', 20)->json();
        $this->getJson('/api/admin/question-reviews/pdfs?review_scope=starter')->assertJsonPath('data.0.pending_count', 5);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
        $ids = array_column($starter['data'], 'id');
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id;
        $batch = $this->getJson($url)->json();
        $this->postJson($url . '/approve', ['snapshot' => $batch['snapshot'], 'flagged' => [], 'question_ids' => $ids])
            ->assertOk()->assertJsonPath('approved_count', 5);
        $this->assertSame(15, QuestionReview::where('status', 'pending')->count());
        $this->getJson('/api/admin/question-reviews?review_scope=starter')->assertJsonPath('total', 0)->assertJsonPath('counts.pending', 0);
        $this->getJson('/api/admin/question-reviews?review_scope=all')->assertJsonPath('total', 15);
    }

    public function test_flagged_starter_questions_are_not_automatically_replaced(): void
    {
        $this->pdfBatch(20);
        $items = $this->getJson('/api/admin/question-reviews?review_scope=starter')->assertOk()->json('data');
        foreach ($items as $index => $item) {
            $this->postJson('/api/admin/question-reviews/' . $item['id'] . '/flag', ['revision' => $item['revision']])->assertOk();
            $this->getJson('/api/admin/question-reviews?review_scope=starter')->assertOk()->assertJsonPath('total', 4 - $index);
        }
        $this->getJson('/api/admin/question-reviews/pdfs?review_scope=starter')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/question-reviews?review_scope=all')->assertOk()->assertJsonPath('total', 15);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
        $this->assertSame(5, QuestionCorrection::where('status', 'needs_manual')->count());
        $this->assertSame('processed', $this->source->fresh()->status);
        $flag = QuestionCorrection::firstOrFail();
        $this->putJson('/api/admin/question-corrections/' . $flag->question_id, [
            'revision' => QuestionReview::findOrFail($flag->question_id)->revision,
            'content' => ['text' => 'Saved for later approval'], 'approve' => false,
        ])->assertOk()->assertJsonPath('status', 'pending');
        $this->getJson('/api/admin/question-reviews?review_scope=starter')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_partial_pdf_approval_rejects_foreign_ids_without_approving_hidden_questions(): void
    {
        $batch = $this->pdfBatch(10);
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id . '/approve';
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => [], 'question_ids' => [str_repeat('f', 64)]])->assertStatus(422);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => [], 'question_ids' => [$batch['questions'][0]['id']]])
            ->assertOk()->assertJsonPath('approved_count', 1);
        $this->assertSame(9, QuestionReview::where('status', 'pending')->count());
    }

    private function flagQuestion(?string $reason = 'Missing denominator'): void
    {
        $this->queue();
        $review = QuestionReview::findOrFail($this->fingerprint);
        $this->postJson('/api/admin/question-reviews/' . $this->fingerprint . '/flag', ['revision' => $review->revision, 'reason' => $reason])
            ->assertOk()->assertJsonPath('status', 'needs_correction');
    }

    public function test_saved_flags_are_durable_idempotent_authorized_and_excluded_from_approval(): void
    {
        $this->flagQuestion();
        $issue = QuestionCorrection::sole();
        $this->assertSame('Missing denominator', $issue->reason);
        $this->assertSame(auth()->id(), $issue->flagged_by);
        $this->assertSame($this->source->source_sha256, $issue->sources[0]['source_sha256']);
        $this->postJson('/api/admin/question-reviews/' . $this->fingerprint . '/flag', ['revision' => 1])->assertOk();
        $this->assertSame(1, QuestionCorrection::count());
        $this->getJson('/api/admin/question-corrections')->assertOk()->assertJsonPath('data.0.status', 'needs_manual')->assertJsonPath('total', 1)->assertJsonPath('data.0.approval', null);
        $this->assertSame([], app(QuestionReviewService::class)->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        $this->decision(true, 2)->assertStatus(409);
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/question-corrections')->assertForbidden();
        $this->postJson('/api/admin/question-reviews/' . $this->fingerprint . '/flag', ['revision' => 2])->assertForbidden();
        $this->putJson('/api/admin/question-corrections/' . $this->fingerprint, ['revision' => 2, 'content' => ['text' => 'Bad']])->assertForbidden();
    }

    public function test_flagging_does_not_queue_a_pdf_or_run_extraction(): void
    {
        $this->flagQuestion();
        $before = $this->source->fresh()->toArray();
        $this->getJson('/api/admin/question-corrections')->assertOk()->assertJsonMissingPath('nightly_at');
        $this->assertSame($before, $this->source->fresh()->toArray());
        $this->assertSame('needs_manual', QuestionCorrection::sole()->status);
        $this->assertSame(0, QuestionCorrection::sole()->attempts);
        $this->assertNull(QuestionCorrection::sole()->retry_sources);
    }

    public function test_changed_retry_result_requires_review_then_resolves_the_issue(): void
    {
        $this->flagQuestion();
        // Simulate a historical in-flight retry so stored jobs can still finish safely.
        QuestionCorrection::sole()->update(['status' => 'processing', 'attempts' => 1, 'retry_sources' => [$this->source->id => $this->source->revision]]);
        $new = hash('sha256', 'changed extraction');
        $this->bank($new); $this->processed([$new]);
        $this->queue();
        app(QuestionCorrectionService::class)->reconcile(app(QuestionReviewService::class)->currentQuestions());
        $this->assertSame('review_needed', QuestionCorrection::sole()->status);
        $this->assertSame($new, QuestionCorrection::sole()->replacement_id);
        $review = QuestionReview::findOrFail($new);
        $this->putJson('/api/admin/question-reviews/' . $new, ['approved' => true, 'revision' => $review->revision])->assertOk();
        $this->assertSame('resolved', QuestionCorrection::sole()->fresh()->status);
    }

    public function test_manual_correction_creates_a_new_unapproved_version_and_preserves_original(): void
    {
        $this->flagQuestion();
        // Flags saved before automation was removed remain editable without a migration.
        QuestionCorrection::sole()->update(['status' => 'queued']);
        $original = QuestionReview::findOrFail($this->fingerprint)->content;
        $revision = QuestionReview::findOrFail($this->fingerprint)->revision;
        $this->putJson('/api/admin/question-corrections/' . $this->fingerprint, ['revision' => $revision,
            'content' => ['instructions' => 'Simplify:', 'math' => ['type' => 'fraction', 'numerator' => ['type' => 'number', 'value' => '2'], 'denominator' => ['type' => 'number', 'value' => '3']]]])
            ->assertOk()->assertJsonPath('status', 'pending');
        $issue = QuestionCorrection::sole();
        $this->assertNotSame($this->fingerprint, $issue->replacement_id);
        $this->assertSame($original, QuestionReview::findOrFail($this->fingerprint)->content);
        $current = app(QuestionReviewService::class)->currentQuestions();
        $this->assertArrayNotHasKey($this->fingerprint, $current);
        $this->assertSame('3', $current[$issue->replacement_id]['content']['math']['denominator']['value']);
        $this->assertSame([], app(QuestionReviewService::class)->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        $review = QuestionReview::findOrFail($issue->replacement_id);
        $this->getJson('/api/admin/question-corrections')->assertOk()
            ->assertJsonPath('data.0.approval.id', $review->id)->assertJsonPath('data.0.approval.revision', $review->revision)
            ->assertJsonPath('data.0.content.math.denominator.value', '3');
        $this->putJson('/api/admin/question-reviews/' . $review->id, ['approved' => true, 'revision' => $review->revision + 1])->assertStatus(409);
        $this->putJson('/api/admin/question-reviews/' . $review->id, ['approved' => true, 'revision' => $review->revision])->assertOk();
        $this->assertSame('resolved', $issue->fresh()->status);
        $this->getJson('/api/admin/question-corrections')->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/question-corrections?status=resolved')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.approval', null);
        $this->assertCount(1, app(QuestionReviewService::class)->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
        Storage::disk('local')->put($this->source->pdf_path, 'changed PDF');
        $this->assertSame([], app(QuestionReviewService::class)->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
    }

    public function test_save_and_approve_resolves_the_correction_in_one_request(): void
    {
        $this->flagQuestion();
        $original = QuestionReview::findOrFail($this->fingerprint)->content;
        $response = $this->putJson('/api/admin/question-corrections/' . $this->fingerprint, [
            'revision' => 2, 'content' => ['text' => '32 and 36'], 'approve' => true,
        ])->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame('resolved', QuestionCorrection::sole()->status);
        $this->assertSame($original, QuestionReview::findOrFail($this->fingerprint)->content);
        $this->assertSame('32 and 36', QuestionReview::findOrFail($response->json('id'))->content['text']);
        $this->getJson('/api/admin/question-corrections')->assertJsonPath('total', 0);
        $this->assertCount(1, app(QuestionReviewService::class)->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
    }

    public function test_save_and_approve_reuses_an_unchanged_saved_draft(): void
    {
        $this->flagQuestion();
        $service = app(QuestionCorrectionService::class);
        $saved = $service->correct($this->fingerprint, 2, ['text' => 'Saved correction'], auth()->id());
        $this->putJson('/api/admin/question-corrections/' . $this->fingerprint, [
            'revision' => 3, 'content' => ['text' => 'Saved correction'], 'approve' => true,
        ])->assertOk()->assertJsonPath('id', $saved->id)->assertJsonPath('status', 'approved');
        $this->assertSame(2, QuestionReview::count());
        $this->assertSame('resolved', QuestionCorrection::sole()->status);
    }

    public function test_approval_failure_rolls_back_the_correction_save(): void
    {
        $this->flagQuestion();
        $service = \Mockery::mock(QuestionReviewService::class)->makePartial();
        $service->shouldReceive('decide')->once()->andThrow(new \Symfony\Component\HttpKernel\Exception\HttpException(409, 'Changed source.'));
        $this->app->instance(QuestionReviewService::class, $service);
        $this->putJson('/api/admin/question-corrections/' . $this->fingerprint, [
            'revision' => 2, 'content' => ['text' => 'Must not persist'], 'approve' => true,
        ])->assertStatus(409);
        $this->assertSame(1, QuestionReview::count());
        $this->assertSame(2, QuestionReview::sole()->revision);
        $this->assertNull(QuestionCorrection::sole()->replacement_id);
        $this->assertNull(QuestionCorrection::sole()->corrected_content);
    }

    public function test_maths_can_be_corrected_as_writing_with_spaces_symbols_and_line_breaks(): void
    {
        $this->flagQuestion();
        $original = QuestionReview::findOrFail($this->fingerprint)->content;
        $text = "32 and 36\nCompare x² and √9.";
        $response = $this->putJson('/api/admin/question-corrections/' . $this->fingerprint, [
            'revision' => QuestionReview::findOrFail($this->fingerprint)->revision,
            'content' => ['instructions' => 'Find the HCF:', 'text' => $text],
        ])->assertOk()->assertJsonPath('status', 'pending');
        $replacement = QuestionReview::findOrFail($response->json('id'));
        $this->assertSame($text, $replacement->content['text']);
        $this->assertArrayNotHasKey('math', $replacement->content);
        $this->assertSame($original, QuestionReview::findOrFail($this->fingerprint)->content);
        $this->getJson('/api/admin/question-corrections')->assertOk()
            ->assertJsonPath('data.0.content.text', $text)->assertJsonPath('data.0.approval.id', $replacement->id);
        $this->assertSame([], app(QuestionReviewService::class)->approvedQuestions($this->lesson->grade_id, $this->lesson->subject_id));
    }

    public function test_correction_rejects_stale_and_unsafe_payloads_and_stale_sources(): void
    {
        $this->flagQuestion();
        $url = '/api/admin/question-corrections/' . $this->fingerprint;
        $this->putJson($url, ['revision' => 1, 'content' => ['text' => 'new']])->assertStatus(409);
        $this->putJson($url, ['revision' => 2, 'content' => ['math' => ['type' => 'html', 'value' => '<script>']]])->assertStatus(422);
        $this->putJson($url, ['revision' => 2, 'content' => ['math' => ['type' => 'fraction', 'numerator' => ['type' => 'number', 'value' => '1']]]])->assertStatus(422);
        $this->putJson($url, ['revision' => 2, 'content' => ['text' => 'x', 'math' => ['type' => 'number', 'value' => '1']]])->assertStatus(422);
        Storage::disk('local')->put($this->source->pdf_path, 'changed PDF');
        $this->putJson($url, ['revision' => 2, 'content' => ['text' => 'new']])->assertStatus(409);
        $this->assertSame('needs_manual', QuestionCorrection::sole()->status);
        $this->assertNull(QuestionCorrection::sole()->corrected_content);
    }

    public function test_reflagged_manual_version_resolves_its_ancestors_without_restoring_old_content(): void
    {
        $this->flagQuestion();
        $service = app(QuestionCorrectionService::class);
        $first = $service->correct($this->fingerprint, 2, ['text' => 'First correction'], auth()->id());
        $service->flag($first->id, $first->revision, auth()->id(), 'Still wrong');
        $second = $service->correct($first->id, $first->fresh()->revision, ['text' => 'Final correction'], auth()->id());
        $current = app(QuestionReviewService::class)->currentQuestions();
        $this->assertArrayNotHasKey($this->fingerprint, $current);
        $this->assertArrayNotHasKey($first->id, $current);
        $this->assertSame('Final correction', $current[$second->id]['content']['text']);
        app(QuestionReviewService::class)->decide($second->id, true, $second->revision, auth()->id(), null);
        $this->assertSame(2, QuestionCorrection::where('status', 'resolved')->count());
        $this->assertSame('First correction', $first->fresh()->content['text']);
    }

    public function test_removed_automation_is_not_registered_or_scheduled(): void
    {
        config(['question_bank.worker_enabled' => true]);
        $commands = \Illuminate\Support\Facades\Artisan::all();
        $this->assertArrayNotHasKey('question-bank:nightly', $commands);
        $this->assertArrayNotHasKey('question-bank:queue-corrections', $commands);
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $this->assertCount(0, array_filter($schedule->events(), fn ($event) => str_contains($event->command ?? '', 'question-bank:')));
    }

    private function pdfBatch(int $count = 3): array
    {
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $template = $bank['questions'][$this->fingerprint];
        $ids = [$this->fingerprint];
        for ($i = 1; $i < $count; $i++) {
            $id = hash('sha256', 'batch-question-' . $i);
            $ids[] = $id;
            $bank['questions'][$id] = array_replace($template, ['id' => $id]);
            $bank['questions'][$id]['origins'][0]['label'] = (string) ($i + 1);
        }
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $this->processed($ids);
        return $this->getJson('/api/admin/question-reviews/pdfs/' . $this->source->id)->assertOk()->json();
    }

    public function test_pdf_batch_includes_every_question_and_leaves_flags_pending(): void
    {
        $batch = $this->pdfBatch(25);
        $this->assertCount(25, $batch['questions']);
        $this->getJson('/api/admin/question-reviews/pdfs')->assertOk()->assertJsonPath('data.0.pending_count', 25);
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id . '/approve';
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => [$this->fingerprint]])
            ->assertOk()->assertJsonPath('approved_count', 24);
        $this->assertSame('needs_correction', QuestionReview::findOrFail($this->fingerprint)->status);
        $this->assertSame(24, QuestionReview::where('status', 'approved')->count());
        $approved = QuestionReview::where('status', 'approved')->first();
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(auth()->id(), $approved->reviewer_id);
        $this->getJson('/api/admin/question-reviews/pdfs')->assertJsonCount(0, 'data');
        // A duplicate request cannot accidentally approve the previously flagged question.
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertStatus(409);
        $this->assertSame('needs_correction', QuestionReview::findOrFail($this->fingerprint)->status);
    }

    public function test_pdf_batch_rejects_stale_review_and_source_without_partial_approval(): void
    {
        $batch = $this->pdfBatch();
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id . '/approve';
        QuestionReview::whereKey($this->fingerprint)->increment('revision');
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertStatus(409);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
        $batch = $this->getJson('/api/admin/question-reviews/pdfs/' . $this->source->id)->json();
        $this->source->increment('revision');
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertStatus(409);
        $batch = $this->getJson('/api/admin/question-reviews/pdfs/' . $this->source->id)->json();
        Storage::disk('local')->put($this->source->pdf_path, 'changed bytes');
        $this->postJson($url, ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertStatus(409);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
    }

    public function test_pdf_batch_requires_staff_and_rejects_foreign_flags(): void
    {
        $batch = $this->pdfBatch();
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id;
        $this->postJson($url . '/approve', ['snapshot' => $batch['snapshot'], 'flagged' => [str_repeat('f', 64)]])->assertStatus(422);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/question-reviews/pdfs')->assertForbidden();
        $this->getJson($url)->assertForbidden();
        $this->postJson($url . '/approve', ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertForbidden();
    }

    public function test_pdf_batch_rejects_changed_diagrams_and_lesson_binding(): void
    {
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $bytes = 'test diagram bytes';
        $hash = hash('sha256', $bytes);
        $path = "structured/diagrams/{$hash}.png";
        Storage::disk('local')->put($path, $bytes);
        $bank['questions'][$this->fingerprint]['diagrams'] = [['sha256' => $hash, 'path' => $path, 'width' => 1, 'height' => 1]];
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $batch = $this->pdfBatch();
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id;
        Storage::disk('local')->put($path, 'changed');
        $this->postJson($url . '/approve', ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertStatus(409);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
        Storage::disk('local')->put($path, $bytes);
        $this->lesson->update(['topic' => 'New topic']);
        $this->postJson($url . '/approve', ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertStatus(409);
        $this->assertSame(0, QuestionReview::where('status', 'approved')->count());
    }

    public function test_pdf_batch_preserves_prior_approvals_and_extraction_issues(): void
    {
        $this->pdfBatch();
        $this->decision(true)->assertOk();
        $original = QuestionReview::findOrFail($this->fingerprint)->toArray();
        $this->source->update(['rejected_count' => 4, 'skipped_count' => 2]);
        $url = '/api/admin/question-reviews/pdfs/' . $this->source->id;
        $batch = $this->getJson($url)->assertJsonPath('source.rejected_count', 4)->assertJsonPath('source.skipped_count', 2)->json();
        $this->postJson($url . '/approve', ['snapshot' => $batch['snapshot'], 'flagged' => []])->assertOk()->assertJsonPath('approved_count', 2);
        $this->assertSame($original, QuestionReview::findOrFail($this->fingerprint)->toArray());
        $this->assertSame(4, $this->source->fresh()->rejected_count);
        $this->getJson('/api/admin/question-reviews/pdfs')->assertJsonCount(0, 'data');
    }

    private function coverageBank(): array
    {
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $source = $this->source->fresh();
        $key = "{$source->grade_id}:{$source->subject_id}:{$source->lesson_id}:question";
        $bank['sources'][$key] = ['status' => 'processed', 'sha256' => $source->source_sha256, 'local_pdf' => $source->pdf_path,
            'grade_id' => $source->grade_id, 'subject_id' => $source->subject_id, 'topic' => $source->topic,
            'rejected' => array_fill(0, 23, ['page' => 1, 'label' => '(a)', 'reason' => 'ambiguous_fraction', 'rect' => ['x' => 20, 'y' => 30, 'width' => 80, 'height' => 50], 'raw_text' => '<unverified>']),
            'skipped_pages' => [['page' => 2, 'reason' => 'no_exercise_heading', 'audit_hint' => 'question_markers_found', 'raw_text' => '(d) Find x.']]];
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        return [$bank, $key];
    }

    public function test_extraction_coverage_is_paginated_filtered_and_cannot_be_approved_as_a_question(): void
    {
        $this->coverageBank();
        $response = $this->getJson('/api/admin/question-reviews?status=rejected')->assertOk()
            ->assertJsonPath('total', 23)->assertJsonPath('last_page', 2)->assertJsonCount(20, 'data')
            ->assertJsonPath('counts.pending', 1)->assertJsonPath('counts.rejected', 23)->assertJsonPath('counts.skipped', 1)
            ->assertJsonPath('data.0.current', false)->assertJsonPath('data.0.coverage.rect.x', 20);
        $this->getJson('/api/admin/question-reviews?status=rejected&page=2')->assertJsonCount(3, 'data');
        $this->getJson('/api/admin/question-reviews?status=skipped')->assertJsonCount(1, 'data')->assertJsonPath('data.0.coverage.audit_hint', 'question_markers_found');
        $this->getJson('/api/admin/question-reviews?status=rejected&lesson_id=999999')->assertJsonPath('total', 0)->assertJsonPath('counts.rejected', 0);
        $this->getJson('/api/admin/question-reviews?status=skipped&grade_id=999999')->assertJsonPath('total', 0);
        $this->getJson('/api/admin/question-reviews?status=skipped&subject_id=999999')->assertJsonPath('total', 0);
        $id = $response->json('data.0.id');
        $this->putJson('/api/admin/question-reviews/' . $id, ['approved' => true, 'revision' => 1])->assertNotFound();
        $this->assertSame(1, QuestionReview::count());
        $this->assertSame('pending', QuestionReview::sole()->status);
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/question-reviews?status=skipped')->assertForbidden();
    }

    public function test_coverage_requires_current_source_hash_path_binding_and_processed_state(): void
    {
        [$bank, $key] = $this->coverageBank();
        foreach (['sha256' => str_repeat('0', 64), 'processor_version' => 'outdated', 'local_pdf' => 'other.pdf', 'topic' => 'Other topic', 'grade_id' => 999, 'status' => 'failed'] as $field => $value) {
            $changed = $bank; $changed['sources'][$key][$field] = $value;
            Storage::disk('local')->put('structured/bank.json', json_encode($changed));
            $this->getJson('/api/admin/question-reviews?status=rejected')->assertOk()->assertJsonPath('total', 0);
        }
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $this->source->update(['status' => 'pending']);
        $this->getJson('/api/admin/question-reviews?status=skipped')->assertJsonPath('total', 0);
        $this->processed([$this->fingerprint]);
        Storage::disk('local')->put($this->source->pdf_path, 'changed PDF');
        $this->getJson('/api/admin/question-reviews?status=skipped')->assertJsonPath('total', 0);
    }

    public function test_lessons_with_no_extracted_questions_remain_available_in_coverage_filters(): void
    {
        [$bank] = $this->coverageBank();
        $bank['questions'] = [];
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $this->processed([]);
        $this->getJson('/api/admin/question-reviews?status=skipped')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('counts.pending', 0)->assertJsonPath('lessons.0.id', $this->lesson->id);
    }

    public function test_issue_retry_queues_one_forced_source_rescan_and_preserves_approvals(): void
    {
        $this->coverageBank(); $this->queue(); $this->decision(true)->assertOk();
        $this->source->update(['rejected_count' => 23, 'skipped_count' => 1]);
        $issue = $this->getJson('/api/admin/question-reviews?status=rejected')->json('data.0');
        $url = '/api/admin/question-reviews/issues/' . $issue['id'] . '/retry';
        $this->postJson($url, ['revision' => $issue['coverage']['source_revision']])->assertStatus(202)
            ->assertJsonPath('status', 'pending')->assertJsonPath('before_count', 1)->assertJsonPath('before_skipped', 1)
            ->assertJson(fn ($json) => $json->whereType('requested_at', 'string')->etc());
        $source = $this->source->fresh();
        $this->assertTrue($source->force_reprocess);
        $this->assertSame(2, $source->revision);
        $this->assertNull($source->question_ids);
        $this->assertSame('approved', QuestionReview::sole()->status);
        $this->postJson($url, ['revision' => 1])->assertStatus(409);
        $this->assertSame(2, $this->source->fresh()->revision);
        $this->getJson('/api/admin/lessons/' . $this->lesson->id . '/question-bank')->assertOk()->assertJsonPath('sources.0.skipped_count', 1)
            ->assertJsonPath('sources.0.retry_scheduled', false)->assertJson(fn ($json) => $json->has('sources.0.requested_at')->etc());
    }

    public function test_issue_retry_rejects_students_stale_revisions_and_changed_pdf_bytes(): void
    {
        $this->coverageBank();
        $issue = $this->getJson('/api/admin/question-reviews?status=skipped')->json('data.0');
        $url = '/api/admin/question-reviews/issues/' . $issue['id'] . '/retry';
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->postJson($url, ['revision' => 1])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $this->postJson($url, ['revision' => 99])->assertStatus(409);
        $this->postJson($url, [])->assertStatus(422);
        Storage::disk('local')->put($this->source->pdf_path, 'changed PDF');
        $this->postJson($url, ['revision' => 1])->assertStatus(409);
        $this->assertFalse($this->source->fresh()->force_reprocess);
        $this->assertSame('processed', $this->source->fresh()->status);
    }

    public function test_diagrams_are_private_and_missing_or_changed_assets_cannot_be_approved(): void
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII=');
        $hash = hash('sha256', $bytes);
        $path = "structured/diagrams/{$hash}.png";
        Storage::disk('local')->put($path, $bytes);
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $bank['questions'][$this->fingerprint]['diagrams'] = [['sha256' => $hash, 'path' => $path, 'width' => 1, 'height' => 1]];
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $url = '/api/admin/question-reviews/' . $this->fingerprint . '/diagrams/0';
        $this->getJson('/api/admin/question-reviews')->assertOk()->assertJsonPath('data.0.content.diagrams.0.sha256', $hash);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($url . '1')->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        Storage::disk('local')->put($path, 'changed');
        $this->getJson($url)->assertNotFound();
        $this->decision(true)->assertStatus(409);
        $this->assertSame('pending', QuestionReview::sole()->status);
        Storage::disk('local')->delete($path);
        $this->getJson($url)->assertNotFound();
        $bank['questions'][$this->fingerprint]['diagrams'][0]['path'] = '../outside.png';
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $this->assertSame([], app(QuestionReviewService::class)->currentQuestions());
    }
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
