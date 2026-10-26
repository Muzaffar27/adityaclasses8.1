<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\Subject;
use App\Models\User;
use App\Services\LessonQuestionBankService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class LessonQuestionBankUploadTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase();
        (require database_path('migrations/2026_10_19_000001_create_lesson_question_sources_table.php'))->up();
        (require database_path('migrations/2026_10_19_000002_create_question_reviews_table.php'))->up();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->closeLearningDatabase();
        parent::tearDown();
    }

    private function lesson(): Lesson
    {
        return Lesson::create(['grade_id' => Grade::create(['name' => 'Grade 8'])->id,
            'subject_id' => Subject::create(['name' => 'Mathematics'])->id,
            'topic' => 'Percentage', 'title' => 'Sale prices', 'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
    }

    private function upload(Lesson $lesson, string $slot = 'question', string $content = 'one'): void
    {
        $this->post("/api/admin/lessons/{$lesson->id}/pdf", ['type' => $slot,
            'pdf' => UploadedFile::fake()->createWithContent('question.pdf', "%PDF-1.4\n{$content}\n%%EOF")])->assertOk();
    }

    private function workerResult(array $job): array
    {
        $source = LessonQuestionSource::findOrFail($job['id']);
        return $job + ['status' => 'processed', 'processor_version' => str_repeat('a', 64),
            'source_sha256' => hash_file('sha256', Storage::disk('local')->path($source->pdf_path)), 'question_ids' => [str_repeat('b', 64)], 'rejected_count' => 0, 'skipped_count' => 1];
    }

    public function test_upload_queues_without_extracting_and_replacement_clears_processed_mark(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $lesson = $this->lesson();
        $this->upload($lesson);
        $source = LessonQuestionSource::sole();
        $this->assertSame('pending', $source->status);
        $this->assertSame(1, $source->revision);
        $this->assertNull($source->processed_at);
        $service = app(LessonQuestionBankService::class);
        $batch = $service->exportWork(str_repeat('a', 64));
        $this->assertSame('processed', $service->complete($this->workerResult($batch['jobs'][0])));
        $this->assertSame(1, $source->fresh()->question_count);
        $this->upload($lesson, content: 'two');
        $source->refresh();
        $this->assertSame('pending', $source->status);
        $this->assertSame(2, $source->revision);
        $this->assertNull($source->processed_at);
        $this->assertNull($source->question_ids);
        $this->getJson("/api/admin/lessons/{$lesson->id}/question-bank")->assertOk()->assertJsonPath('sources.0.status', 'pending');
    }

    public function test_student_cannot_upload_or_read_staff_processing_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $lesson = $this->lesson();
        $this->post("/api/admin/lessons/{$lesson->id}/pdf", ['type' => 'question', 'pdf' => UploadedFile::fake()->createWithContent('q.pdf', "%PDF-1.4\n%%EOF")])->assertForbidden();
        $this->getJson("/api/admin/lessons/{$lesson->id}/question-bank")->assertForbidden();
        $this->assertSame(0, LessonQuestionSource::count());
    }

    public function test_both_question_slots_are_independent_and_answer_pdf_does_not_queue(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $lesson = $this->lesson();
        $this->upload($lesson, 'answer');
        $this->assertSame(0, LessonQuestionSource::count());
        $this->upload($lesson, 'question');
        $this->upload($lesson, 'question2');
        $this->assertSame(2, LessonQuestionSource::count());
        $this->assertEqualsCanonicalizing(['question', 'question2'], LessonQuestionSource::pluck('slot')->all());
    }

    public function test_result_from_a_replaced_pdf_cannot_overwrite_the_new_request(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $lesson = $this->lesson(); $this->upload($lesson);
        $service = app(LessonQuestionBankService::class);
        $batch = $service->exportWork(str_repeat('a', 64));
        $old = $this->workerResult($batch['jobs'][0]);
        $this->upload($lesson, content: 'new');
        $this->assertSame('superseded', $service->complete($old));
        $this->assertSame('pending', LessonQuestionSource::sole()->status);
    }

    public function test_changed_file_bytes_are_checked_before_accepting_worker_results(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $lesson = $this->lesson(); $this->upload($lesson);
        $service = app(LessonQuestionBankService::class);
        $batch = $service->exportWork(str_repeat('a', 64));
        $old = $this->workerResult($batch['jobs'][0]);
        Storage::disk('local')->put(LessonQuestionSource::sole()->pdf_path, "%PDF-1.4\nchanged\n%%EOF");
        $this->assertSame('superseded', $service->complete($old));
        $this->assertSame('pending', LessonQuestionSource::sole()->status);
    }

    public function test_removal_queues_bank_retirement_and_is_acknowledged_once(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $lesson = $this->lesson(); $this->upload($lesson);
        $this->deleteJson("/api/admin/lessons/{$lesson->id}/pdf/question")->assertOk();
        $service = app(LessonQuestionBankService::class);
        $batch = $service->exportWork(str_repeat('a', 64));
        $this->assertSame('remove', $batch['jobs'][0]['action']);
        $this->assertSame([], $batch['manifest']);
        $this->assertSame('removed', $service->complete($batch['jobs'][0] + ['status' => 'removed']));
        $this->assertSame([], $service->exportWork(str_repeat('a', 64))['jobs']);
    }

    public function test_bulk_topic_changes_are_reconciled_and_stale_results_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $lesson = $this->lesson(); $this->upload($lesson);
        $service = app(LessonQuestionBankService::class);
        $batch = $service->exportWork(str_repeat('a', 64));
        $old = $this->workerResult($batch['jobs'][0]);
        Lesson::whereKey($lesson->id)->update(['topic' => 'A different topic']);
        $this->assertSame('superseded', $service->complete($old));
        $this->assertSame('A different topic', LessonQuestionSource::sole()->topic);
    }

    public function test_failures_are_visible_and_retried_with_backoff(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $lesson = $this->lesson(); $this->upload($lesson);
        $service = app(LessonQuestionBankService::class);
        $job = $service->exportWork(str_repeat('a', 64))['jobs'][0];
        $this->assertSame('failed', $service->complete($job + ['status' => 'failed']));
        $this->assertSame([], $service->exportWork(str_repeat('a', 64))['jobs']);
        LessonQuestionSource::query()->update(['next_attempt_at' => now()->subSecond()]);
        $this->assertCount(1, $service->exportWork(str_repeat('a', 64))['jobs']);
    }

    public function test_worker_bridge_refuses_result_files_outside_its_private_results_directory(): void
    {
        Storage::disk('local')->put('outside.json', '{"results":[]}');
        $this->artisan('question-bank:import', ['file' => 'outside.json'])->assertExitCode(1);
    }

    public function test_worker_export_rejects_a_production_environment(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('question-bank:export', ['--processor' => str_repeat('a', 64)])->assertExitCode(1);
    }
}
