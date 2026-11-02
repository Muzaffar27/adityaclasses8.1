<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\QuestionCorrection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class QuestionReviewService
{
    public function currentQuestions(?array $sourceIds = null): array
    {
        if (!Schema::hasTable('question_reviews')) return [];
        $path = Storage::disk('local')->path('structured/bank.json');
        if (!is_file($path)) return [];
        $bank = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $valid = $this->currentSources($sourceIds);
        $current = [];
        foreach ($bank['questions'] ?? [] as $question) {
            if (empty($question['active']) || !preg_match('/^[a-f0-9]{64}$/', $question['id'] ?? '')) continue;
            if (!$this->diagramsAvailable($question['diagrams'] ?? [])) continue;
            $origins = [];
            foreach ($question['origins'] ?? [] as $origin) {
                $entry = $valid[$origin['source_key']] ?? null;
                if (!$entry || !hash_equals($entry['source']->source_sha256, $origin['source_sha256'])
                    || (int) $question['grade_id'] !== (int) $entry['source']->grade_id || (int) $question['subject_id'] !== (int) $entry['source']->subject_id
                    || !in_array($question['id'], $entry['source']->question_ids ?? [], true)) continue;
                $origins[] = [
                    'source_id' => $entry['source']->id, 'lesson_id' => $entry['lesson']->id,
                    'lesson_title' => $entry['lesson']->title, 'topic' => $entry['lesson']->topic,
                    'slot' => $entry['source']->slot, 'page' => $origin['page'], 'label' => $origin['label'],
                ];
            }
            if ($origins) $current[$question['id']] = ['id' => $question['id'], 'grade_id' => $question['grade_id'], 'subject_id' => $question['subject_id'],
                'content' => array_intersect_key($question, array_flip(['instructions', 'text', 'math', 'diagrams'])), 'origins' => $origins];
        }
        $corrections = app(QuestionCorrectionService::class);
        $superseded = [];
        if ($corrections->available()) foreach (QuestionCorrection::whereNotNull('corrected_content')->whereNotNull('replacement_id')->orderBy('flagged_at')->get() as $issue) {
            if ($sourceIds !== null && !array_intersect($sourceIds, array_column($issue->sources, 'id'))) continue;
            $origins = $corrections->validOrigins($issue);
            if (!$origins || !$this->diagramsAvailable($issue->corrected_content['diagrams'] ?? [])) continue;
            $original = $issue->original;
            $current[$issue->replacement_id] = ['id' => $issue->replacement_id, 'grade_id' => $original['grade_id'],
                'subject_id' => $original['subject_id'], 'content' => $issue->corrected_content, 'origins' => $origins, 'correction_of' => $issue->question_id];
            $superseded[] = $issue->question_id;
        }
        foreach ($superseded as $id) unset($current[$id]);
        return $current;
    }

    private function currentSources(?array $sourceIds = null): array
    {
        $sources = LessonQuestionSource::whereIn('status', ['processed', 'processed_with_issues'])->whereNotNull('source_sha256')
            ->when($sourceIds !== null, fn ($query) => $query->whereIn('id', $sourceIds))->get();
        $lessons = Lesson::whereIn('id', $sources->pluck('lesson_id'))->get()->keyBy('id');
        $valid = [];
        foreach ($sources as $source) {
            $lesson = $lessons->get($source->lesson_id);
            $field = LessonQuestionBankService::FIELDS[$source->slot];
            if (!$lesson || !$lesson->is_active || $lesson->{$field} !== $source->pdf_path || $lesson->topic !== $source->topic
                || (int) $lesson->grade_id !== (int) $source->grade_id || (int) $lesson->subject_id !== (int) $source->subject_id) continue;
            $file = Storage::disk('local')->path($source->pdf_path);
            if (!is_file($file) || !hash_equals($source->source_sha256, hash_file('sha256', $file))) continue;
            $key = "{$source->grade_id}:{$source->subject_id}:{$source->lesson_id}:{$source->slot}";
            $valid[$key] = ['source' => $source, 'lesson' => $lesson];
        }
        return $valid;
    }

    public function currentExtractionIssues(): array
    {
        $path = Storage::disk('local')->path('structured/bank.json');
        if (!is_file($path)) return [];
        $bank = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $issues = [];
        foreach ($this->currentSources() as $key => $entry) {
            $source = $entry['source']; $lesson = $entry['lesson'];
            $record = $bank['sources'][$key] ?? null;
            if (!$record || !in_array($record['status'] ?? '', ['processed', 'processed_with_issues'], true)
                || ($record['processor_version'] ?? null) !== $source->processor_version
                || ($record['sha256'] ?? '') !== $source->source_sha256 || ($record['local_pdf'] ?? '') !== $source->pdf_path
                || (int) ($record['grade_id'] ?? 0) !== (int) $lesson->grade_id || (int) ($record['subject_id'] ?? 0) !== (int) $lesson->subject_id
                || ($record['topic'] ?? '') !== $lesson->topic) continue;
            foreach (['rejected' => 'rejected', 'skipped_pages' => 'skipped'] as $field => $kind) {
                foreach ($record[$field] ?? [] as $index => $issue) {
                    $page = $issue['page'] ?? null;
                    if (!is_int($page) || $page < 1) continue;
                    $version = $record['processor_version'] ?? '';
                    $issues[] = ['id' => hash('sha256', "issue:{$key}:{$source->source_sha256}:{$version}:{$kind}:{$index}"),
                        'grade_id' => $lesson->grade_id, 'subject_id' => $lesson->subject_id, 'status' => $kind, 'current' => false,
                        'content' => [], 'coverage' => ['kind' => $kind, 'source_revision' => $source->revision, 'reason' => $issue['reason'] ?? 'unknown',
                            'audit_hint' => $issue['audit_hint'] ?? 'inspection_required', 'raw_text' => $issue['raw_text'] ?? '', 'rect' => $issue['rect'] ?? null],
                        'origins' => [['source_id' => $source->id, 'lesson_id' => $lesson->id, 'lesson_title' => $lesson->title,
                            'topic' => $lesson->topic, 'slot' => $source->slot, 'page' => $page, 'label' => $issue['label'] ?? 'Whole page']]];
                }
            }
        }
        return $issues;
    }

    public function retryExtractionIssue(string $id, int $revision): array
    {
        return DB::transaction(function () use ($id, $revision) {
            $issue = collect($this->currentExtractionIssues())->firstWhere('id', $id);
            abort_unless($issue, 409, 'This issue changed or its PDF is already processing. Refresh the queue.');
            $origin = $issue['origins'][0];
            $source = LessonQuestionSource::whereKey($origin['source_id'])->lockForUpdate()->firstOrFail();
            abort_unless($source->revision === $revision && in_array($source->status, ['processed', 'processed_with_issues'], true), 409, 'This PDF changed or is already processing. Refresh the queue.');
            // Recheck source binding and bytes after obtaining the lock.
            abort_unless(collect($this->currentExtractionIssues())->contains('id', $id), 409, 'This extraction report is no longer current. Refresh the queue.');
            $result = ['lesson_id' => $source->lesson_id, 'slot' => $source->slot, 'lesson_title' => $origin['lesson_title'],
                'before_count' => $source->question_count, 'before_rejected' => $source->rejected_count, 'before_skipped' => $source->skipped_count];
            $source->update(['status' => 'pending', 'revision' => $source->revision + 1, 'force_reprocess' => true,
                'question_ids' => null, 'question_count' => 0, 'processed_at' => null, 'requested_at' => now(),
                'claimed_at' => null, 'next_attempt_at' => null, 'last_error' => null, 'attempts' => 0]);
            return $result + ['status' => 'pending', 'revision' => $source->revision, 'requested_at' => $source->requested_at?->toIso8601String()];
        });
    }

    private function diagramsAvailable(array $diagrams): bool
    {
        if (count($diagrams) > 12) return false;
        foreach ($diagrams as $diagram) {
            $hash = $diagram['sha256'] ?? '';
            if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash) || ($diagram['path'] ?? '') !== "structured/diagrams/{$hash}.png") return false;
            $file = Storage::disk('local')->path($diagram['path']);
            if (!is_file($file) || !is_readable($file) || !hash_equals($hash, hash_file('sha256', $file))) return false;
        }
        return true;
    }

    public function synchronize(): array
    {
        if (!Schema::hasTable('question_reviews')) return [];
        $current = $this->currentQuestions();
        foreach ($current as $id => $question) {
            $review = QuestionReview::firstOrCreate(['id' => $id], $question + ['status' => 'pending']);
            if ($review->origins !== $question['origins']) $review->update(['origins' => $question['origins']]);
        }
        foreach (QuestionReview::where('status', 'reprocessing')->get() as $review) {
            DB::transaction(function () use ($review, $current) {
                $locked = QuestionReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'reprocessing') return;
                $pending = false; $failed = false;
                foreach ($locked->retry_sources ?? [] as $id => $revision) {
                    $source = LessonQuestionSource::find($id);
                    if (!$source || $source->status === 'removed') continue;
                    if ($source->revision < $revision || in_array($source->status, ['pending', 'processing'], true)) $pending = true;
                    if ($source->status === 'failed') $failed = true;
                }
                if ($pending || $failed) return;
                $same = isset($current[$locked->id]);
                $locked->update(['status' => $same ? 'review_again' : 'needs_attention', 'revision' => $locked->revision + 1,
                    'retry_outcome' => $same ? 'unchanged' : 'not_in_current_extraction']);
            });
        }
        return $current;
    }

    public function decide(string $id, bool $yes, int $revision, int $reviewer, ?string $note): QuestionReview
    {
        $current = $this->synchronize();
        return DB::transaction(function () use ($id, $yes, $revision, $reviewer, $note, $current) {
            $review = QuestionReview::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($review->status === 'approved' && $yes) return $review; // Repeated clicks do not create another approval.
            abort_unless($review->revision === $revision && in_array($review->status, ['pending', 'review_again'], true) && isset($current[$id]), 409, 'This question changed or is being processed. Refresh the review queue.');
            if ($yes) {
                $review->update(['status' => 'approved', 'reviewer_id' => $reviewer, 'approved_at' => now(),
                    'revision' => $review->revision + 1, 'review_note' => $note]);
                $this->resolveCorrection($id);
            } else {
                $retries = [];
                foreach (array_unique(array_column($current[$id]['origins'], 'source_id')) as $sourceId) {
                    $source = LessonQuestionSource::whereKey($sourceId)->lockForUpdate()->firstOrFail();
                    $source->update(['status' => 'pending', 'revision' => $source->revision + 1, 'force_reprocess' => true,
                        'question_ids' => null, 'question_count' => 0, 'processed_at' => null, 'requested_at' => now(), 'next_attempt_at' => null]);
                    $retries[$sourceId] = $source->revision;
                }
                $review->update(['status' => 'reprocessing', 'review_cycles' => $review->review_cycles + 1, 'retry_sources' => $retries,
                    'retry_outcome' => null, 'review_note' => $note, 'reviewer_id' => $reviewer, 'rejected_at' => now(), 'revision' => $review->revision + 1]);
            }
            return $review->fresh();
        });
    }

    public function pdfReview(int $sourceId): array
    {
        $current = $this->synchronize();
        $entry = collect($this->currentSources())->first(fn ($entry) => $entry['source']->id === $sourceId);
        abort_unless($entry, 409, 'This PDF changed or is being processed. Refresh the PDF list.');
        $source = $entry['source'];
        $questions = QuestionReview::whereIn('id', array_keys($current))->orderBy('id')->get()
            ->filter(fn ($review) => in_array($sourceId, array_column($current[$review->id]['origins'], 'source_id'), true))
            ->map(fn ($review) => ['id' => $review->id, 'revision' => $review->revision, 'status' => $review->status,
                'content' => $current[$review->id]['content'],
                'origin' => collect($current[$review->id]['origins'])->firstWhere('source_id', $sourceId)])->values();
        $snapshot = hash('sha256', json_encode([$source->id, $source->revision, $source->source_sha256,
            $questions->map(fn ($q) => [$q['id'], $q['revision'], $q['status']])->all()], JSON_THROW_ON_ERROR));
        return ['source' => ['id' => $source->id, 'lesson_id' => $source->lesson_id, 'lesson_title' => $entry['lesson']->title,
            'slot' => $source->slot, 'topic' => $source->topic, 'rejected_count' => $source->rejected_count, 'skipped_count' => $source->skipped_count],
            'snapshot' => $snapshot, 'questions' => $questions->sortBy(fn ($q) => sprintf('%06d:%s', $q['origin']['page'], $q['origin']['label']), SORT_NATURAL)->values()];
    }

    public function approvePdf(int $sourceId, string $snapshot, array $flagged, int $reviewer, ?array $questionIds = null): int
    {
        return DB::transaction(function () use ($sourceId, $snapshot, $flagged, $reviewer, $questionIds) {
            $source = LessonQuestionSource::whereKey($sourceId)->lockForUpdate()->firstOrFail();
            // Lock in a stable order, including canonical questions shared by multiple PDFs.
            QuestionReview::whereIn('id', $source->question_ids ?? [])->orderBy('id')->lockForUpdate()->get();
            $batch = $this->pdfReview($sourceId);
            QuestionReview::whereIn('id', $batch['questions']->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $batch = $this->pdfReview($sourceId);
            abort_unless(hash_equals($batch['snapshot'], $snapshot), 409, 'This PDF or its reviews changed. Reload it before approving.');
            $eligible = $batch['questions']->whereIn('status', ['pending', 'review_again'])->pluck('id')->all();
            abort_if(array_diff($flagged, $eligible), 422, 'Flagged questions must belong to this PDF and still need review.');
            $requested = $questionIds ?? $eligible;
            abort_if(array_diff($requested, $eligible), 422, 'Only current questions displayed for this PDF can be approved.');
            $ids = array_values(array_diff($requested, $flagged));
            $current = $flagged ? $this->currentQuestions() : [];
            foreach ($flagged as $id) app(QuestionCorrectionService::class)->flag($id,
                $batch['questions']->firstWhere('id', $id)['revision'], $reviewer, null, $current);
            foreach ($ids as $id) {
                $review = QuestionReview::findOrFail($id);
                $review->update(['status' => 'approved', 'reviewer_id' => $reviewer, 'approved_at' => now(),
                    'revision' => $review->revision + 1, 'review_note' => null]);
                $this->resolveCorrection($id);
            }
            return count($ids);
        }, 3);
    }

    public function approvedQuestions(int $grade, int $subject): array
    {
        if (!Schema::hasTable('question_reviews')) return [];
        $current = $this->currentQuestions();
        $approved = QuestionReview::where('grade_id', $grade)->where('subject_id', $subject)->where('status', 'approved')->pluck('id')->all();
        return array_values(array_filter($current, fn ($q) => in_array($q['id'], $approved, true)));
    }

    private function resolveCorrection(string $id): void
    {
        if (!Schema::hasTable('question_corrections')) return;
        $ids = [$id]; $seen = [];
        while ($ids) {
            $issues = QuestionCorrection::whereIn('replacement_id', $ids)->where('status', 'review_needed')->get();
            $ids = [];
            foreach ($issues as $issue) {
                if (isset($seen[$issue->question_id])) continue;
                $seen[$issue->question_id] = true;
                $issue->update(['status' => 'resolved']);
                $ids[] = $issue->question_id;
            }
        }
    }
}
