<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class LessonQuestionBankService
{
    public const FIELDS = ['question' => 'question_pdf_path', 'question2' => 'question_pdf_2_path'];

    public function synchronize(Lesson $lesson, bool $deleted = false): void
    {
        // Preserve normal lesson operations until the additive migration has been applied.
        if (!Schema::hasTable('lesson_question_sources')) return;
        foreach (self::FIELDS as $slot => $field) {
            $path = !$deleted && $lesson->is_active ? $lesson->{$field} : null;
            if (!$path && !LessonQuestionSource::where('lesson_id', $lesson->id)->where('slot', $slot)->exists()) continue;
            DB::transaction(function () use ($lesson, $slot, $path) {
                $source = LessonQuestionSource::firstOrCreate(['lesson_id' => $lesson->id, 'slot' => $slot], [
                    'grade_id' => $lesson->grade_id, 'subject_id' => $lesson->subject_id,
                    'topic' => $lesson->topic, 'status' => 'removed',
                ]);
                $source = LessonQuestionSource::whereKey($source->id)->lockForUpdate()->firstOrFail();
                if ($source->pdf_path === $path && (int) $source->grade_id === (int) $lesson->grade_id
                    && (int) $source->subject_id === (int) $lesson->subject_id && $source->topic === $lesson->topic) return;
                $source->fill([
                    'grade_id' => $lesson->grade_id, 'subject_id' => $lesson->subject_id, 'topic' => $lesson->topic,
                    'pdf_path' => $path, 'revision' => $source->revision + 1,
                    'status' => $path ? 'pending' : 'removed', 'source_sha256' => null,
                    'question_ids' => null, 'question_count' => 0, 'rejected_count' => 0, 'skipped_count' => 0,
                    'processed_at' => null, 'claimed_at' => null, 'next_attempt_at' => null,
                    'last_error' => null, 'attempts' => 0, 'requested_at' => now(),
                    'force_reprocess' => false,
                ])->save();
            });
        }
    }

    public function reconcile(): void
    {
        Lesson::query()->where(function ($query) {
            $query->whereNotNull('question_pdf_path')->orWhereNotNull('question_pdf_2_path')
                ->orWhereIn('id', LessonQuestionSource::query()->select('lesson_id'));
        })->chunkById(100, function ($lessons) {
            foreach ($lessons as $lesson) $this->synchronize($lesson);
        });
        // Query-builder bulk deletes/renames do not fire model events; reconcile them too.
        LessonQuestionSource::whereNotIn('lesson_id', Lesson::query()->select('id'))
            ->where('status', '!=', 'removed')->update([
                'status' => 'removed', 'pdf_path' => null, 'source_sha256' => null,
                'question_ids' => null, 'question_count' => 0, 'processed_at' => null,
                'revision' => DB::raw('revision + 1'), 'updated_at' => now(),
            ]);
    }

    public function exportWork(string $processor): array
    {
        $this->reconcile();
        // A parser update must invalidate the processed marker as well as a PDF update.
        LessonQuestionSource::whereIn('status', ['processed', 'processed_with_issues'])
            ->where(function ($query) use ($processor) { $query->whereNull('processor_version')->orWhere('processor_version', '!=', $processor); })
            ->update(['status' => 'pending', 'question_ids' => null, 'question_count' => 0, 'processed_at' => null, 'updated_at' => now()]);

        return DB::transaction(function () {
            $jobs = LessonQuestionSource::where(function ($query) {
                $query->where(function ($pending) {
                    $pending->where('status', 'pending')
                        ->orWhere(fn ($retry) => $retry->where('status', 'failed')->where('next_attempt_at', '<=', now()))
                        ->orWhere(fn ($stale) => $stale->where('status', 'processing')->where('claimed_at', '<', now()->subMinutes(10)));
                })->orWhere(fn ($removed) => $removed->whereNull('pdf_path')->where('status', 'removed')->whereNull('processed_at'));
            })->lockForUpdate()->get();
            foreach ($jobs as $source) $source->update(['status' => 'processing', 'claimed_at' => now(), 'attempts' => $source->attempts + 1]);
            $manifest = LessonQuestionSource::whereNotNull('pdf_path')->where('status', '!=', 'removed')->get()->map(fn ($source) => [
                'lesson_id' => (int) $source->lesson_id, 'grade_id' => (int) $source->grade_id,
                'subject_id' => (int) $source->subject_id, 'topic' => $source->topic,
                'slot' => $source->slot, 'local_pdf' => $source->pdf_path, 'force_reprocess' => (bool) $source->force_reprocess,
            ])->all();
            return ['jobs' => $jobs->map(fn ($source) => ['id' => $source->id, 'revision' => $source->revision, 'lesson_id' => (int) $source->lesson_id, 'slot' => $source->slot, 'action' => $source->pdf_path ? 'process' : 'remove'])->all(), 'manifest' => $manifest];
        });
    }

    public function complete(array $result): string
    {
        $existing = LessonQuestionSource::find($result['id']);
        if (!$existing) return 'superseded';
        $lesson = Lesson::find($existing->lesson_id);
        if ($lesson) $this->synchronize($lesson);
        return DB::transaction(function () use ($result, $lesson) {
            $source = LessonQuestionSource::whereKey($result['id'])->lockForUpdate()->firstOrFail();
            if ($source->revision !== (int) $result['revision'] || $source->status !== 'processing') return 'superseded';
            if (($result['status'] ?? null) === 'removed' && !$source->pdf_path) {
                $source->update(['status' => 'removed', 'processed_at' => now(), 'question_ids' => null, 'question_count' => 0]);
                return 'removed';
            }
            if (($result['status'] ?? null) === 'failed') {
                $source->update(['status' => 'failed', 'last_error' => 'Question extraction failed; the local worker will retry.', 'next_attempt_at' => now()->addMinute()]);
                return 'failed';
            }
            if (!$lesson) return 'superseded';
            if (!in_array($result['status'] ?? null, ['processed', 'processed_with_issues'], true)) throw new \InvalidArgumentException('Invalid source processing status.');
            $path = Storage::disk('local')->path($source->pdf_path);
            if (!is_file($path) || !hash_equals(hash_file('sha256', $path), (string) ($result['source_sha256'] ?? ''))) {
                $source->update(['status' => 'pending', 'revision' => $source->revision + 1, 'source_sha256' => null, 'question_ids' => null, 'question_count' => 0, 'processed_at' => null]);
                return 'superseded';
            }
            $ids = array_values(array_unique($result['question_ids'] ?? []));
            foreach ($ids as $id) if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/', $id)) throw new \InvalidArgumentException('Invalid question fingerprint.');
            $source->update([
                'status' => $result['status'], 'source_sha256' => $result['source_sha256'],
                'processor_version' => $result['processor_version'], 'question_ids' => $ids, 'question_count' => count($ids),
                'rejected_count' => (int) ($result['rejected_count'] ?? 0), 'skipped_count' => (int) ($result['skipped_count'] ?? 0),
                'processed_at' => now(), 'next_attempt_at' => null, 'last_error' => null,
                'force_reprocess' => false,
            ]);
            return $result['status'];
        });
    }
}
