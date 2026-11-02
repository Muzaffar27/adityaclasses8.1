<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\QuestionCorrection;
use App\Models\QuestionReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class QuestionCorrectionService
{
    public function available(): bool { return Schema::hasTable('question_corrections'); }

    public function flag(string $id, int $revision, int $reviewer, ?string $reason, ?array $current = null): QuestionReview
    {
        abort_unless($this->available(), 503, 'Apply the question-correction migration first.');
        if ($current === null) {
            $review = QuestionReview::findOrFail($id);
            $current = app(QuestionReviewService::class)->currentQuestions(array_values(array_unique(array_column($review->origins, 'source_id'))));
        }
        return DB::transaction(function () use ($id, $revision, $reviewer, $reason, $current) {
            $review = QuestionReview::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($review->status === 'needs_correction' && QuestionCorrection::find($id)) return $review;
            abort_unless($review->revision === $revision && in_array($review->status, ['pending', 'review_again'], true) && isset($current[$id]), 409, 'This question changed. Reload before flagging.');
            $question = $current[$id];
            $sources = LessonQuestionSource::whereIn('id', array_column($question['origins'], 'source_id'))->orderBy('id')->lockForUpdate()->get();
            $fresh = app(QuestionReviewService::class)->currentQuestions($sources->pluck('id')->all());
            abort_unless(isset($fresh[$id]) && $fresh[$id]['content'] === $question['content'], 409, 'This source changed. Reload before flagging.');
            $question = $fresh[$id];
            QuestionCorrection::create(['question_id' => $id, 'status' => 'needs_manual', 'reason' => $reason, 'flagged_by' => $reviewer, 'flagged_at' => now(),
                'original' => $question, 'sources' => $sources->map(fn ($s) => $s->only(['id', 'source_sha256', 'revision', 'lesson_id', 'slot', 'pdf_path', 'grade_id', 'subject_id', 'topic']))->all()]);
            $review->update(['status' => 'needs_correction', 'review_note' => $reason, 'reviewer_id' => $reviewer,
                'rejected_at' => now(), 'revision' => $review->revision + 1]);
            return $review->fresh();
        }, 3);
    }

    // Only validate the PDFs attached to this correction, never the entire bank.
    public function validOrigins(QuestionCorrection $issue, bool $processed = true): array
    {
        $valid = [];
        foreach ($issue->sources as $snapshot) {
            $source = LessonQuestionSource::find($snapshot['id']);
            $lesson = $source ? Lesson::find($source->lesson_id) : null;
            $ready = $source && (in_array($source->status, ['processed', 'processed_with_issues'], true) || ($source->status === 'failed' && !$source->next_attempt_at));
            if (!$source || !$lesson || !$lesson->is_active || ($processed && !$ready)) continue;
            foreach (['lesson_id', 'slot', 'pdf_path', 'grade_id', 'subject_id', 'topic'] as $field) if ($source->{$field} != $snapshot[$field]) continue 2;
            $field = LessonQuestionBankService::FIELDS[$source->slot];
            if ($lesson->{$field} !== $source->pdf_path || $lesson->topic !== $source->topic || (int) $lesson->grade_id !== (int) $source->grade_id || (int) $lesson->subject_id !== (int) $source->subject_id) continue;
            $file = Storage::disk('local')->path($source->pdf_path);
            if (!is_file($file) || !hash_equals($snapshot['source_sha256'], hash_file('sha256', $file))) continue;
            foreach ($issue->original['origins'] as $origin) if ($origin['source_id'] === $source->id) $valid[] = $origin;
        }
        return $valid;
    }

    // Finish previously claimed correction jobs; new flags use manual correction only.
    public function reconcile(array $current): void
    {
        if (!$this->available()) return;
        foreach (QuestionCorrection::where('status', 'processing')->get() as $candidate) {
            DB::transaction(function () use ($candidate, $current) {
                $issue = QuestionCorrection::whereKey($candidate->question_id)->lockForUpdate()->firstOrFail();
                if ($issue->status !== 'processing') return;
                foreach ($issue->retry_sources ?? [] as $id => $revision) {
                    $source = LessonQuestionSource::find($id);
                    if (!$source || $source->revision !== $revision) { $issue->update(['status' => 'stale', 'outcome' => 'source_changed']); return; }
                    if ($source && $source->revision === $revision && in_array($source->status, ['pending', 'processing'], true)) return;
                    if ($source && $source->revision === $revision && $source->status === 'failed' && $source->next_attempt_at) return;
                }
                if (!$this->validOrigins($issue, false)) { $issue->update(['status' => 'stale', 'outcome' => 'source_changed']); return; }
                if (isset($current[$issue->question_id]) && array_intersect(array_column($current[$issue->question_id]['origins'], 'source_id'), array_keys($issue->retry_sources ?? []))) {
                    $issue->update(['status' => 'needs_manual', 'outcome' => 'unchanged']); return;
                }
                $matches = collect($current)->filter(function ($q) use ($issue) {
                    foreach ($q['origins'] as $origin) foreach ($issue->original['origins'] as $old) {
                        if (isset($issue->retry_sources[$origin['source_id']]) && $origin['source_id'] === $old['source_id'] && $origin['page'] === $old['page'] && $origin['label'] === $old['label']) return true;
                    }
                    return false;
                });
                $issue->update($matches->count() === 1 ? ['status' => 'review_needed', 'outcome' => 'changed', 'replacement_id' => $matches->first()['id']]
                    : ['status' => 'needs_manual', 'outcome' => 'not_recovered']);
            });
        }
    }

    public function correct(string $id, int $revision, array $content, int $reviewer, bool $approve = false): QuestionReview
    {
        $content = $this->validateContent($content);
        return DB::transaction(function () use ($id, $revision, $content, $reviewer, $approve) {
            $issue = QuestionCorrection::whereKey($id)->lockForUpdate()->firstOrFail();
            $original = QuestionReview::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($original->revision === $revision && in_array($issue->status, ['queued', 'needs_manual', 'review_needed'], true), 409, 'This correction changed or is processing. Reload it.');
            LessonQuestionSource::whereIn('id', array_column($issue->sources, 'id'))->orderBy('id')->lockForUpdate()->get();
            $origins = $this->validOrigins($issue);
            abort_unless($origins, 409, 'This source changed or is processing. Review its current extraction.');
            // Preserve original figures; no arbitrary asset paths can be submitted by an editor.
            $content += array_intersect_key($issue->original['content'], ['diagrams' => true]);
            if ($approve && $content === $issue->corrected_content) {
                $saved = QuestionReview::find($issue->replacement_id);
                abort_unless($saved && $saved->content === $content, 409, 'The saved correction changed. Reload it.');
                return app(QuestionReviewService::class)->decide($saved->id, true, $saved->revision, $reviewer, null);
            }
            abort_if($content === $issue->corrected_content, 422, 'Make a change before saving another correction.');
            $replacement = hash('sha256', 'tutor-correction-v1:' . $id . ':' . ($original->revision + 1) . ':' . json_encode($content, JSON_THROW_ON_ERROR));
            $review = QuestionReview::create(['id' => $replacement, 'grade_id' => $original->grade_id, 'subject_id' => $original->subject_id,
                'content' => $content, 'origins' => $origins, 'status' => 'pending']);
            $issue->update(['status' => 'review_needed', 'replacement_id' => $replacement, 'corrected_content' => $content,
                'corrected_by' => $reviewer, 'corrected_at' => now(), 'outcome' => 'manual_correction']);
            $original->increment('revision');
            $review = $review->fresh();
            if ($approve) return app(QuestionReviewService::class)->decide($review->id, true, $review->revision, $reviewer, null);
            return $review;
        }, 3);
    }

    public function validateContent(array $content): array
    {
        $instructions = $content['instructions'] ?? '';
        abort_unless(is_string($instructions) && mb_strlen($instructions) <= 5000, 422, 'Instructions are too long.');
        $text = $content['text'] ?? null; $math = $content['math'] ?? null;
        abort_unless((is_string($text) && trim($text) !== '' && $math === null) || ($text === null && is_array($math)), 422, 'Supply question text or a mathematical expression.');
        if ($text !== null) { abort_unless(mb_strlen($text) <= 15000, 422, 'Question text is too long.'); return ['instructions' => $instructions, 'text' => $text]; }
        $nodes = 0;
        $validate = function ($node, $depth = 0) use (&$validate, &$nodes) {
            abort_unless(is_array($node) && $depth <= 12 && ++$nodes <= 300, 422, 'The expression is too large.');
            $type = $node['type'] ?? '';
            if (in_array($type, ['number', 'variable', 'operator'], true)) {
                abort_unless(is_string($node['value'] ?? null) && mb_strlen($node['value']) <= 100 && trim($node['value']) !== '', 422, 'Each maths value needs text.');
                return ['type' => $type, 'value' => $node['value']];
            }
            if ($type === 'expression') {
                abort_unless(is_array($node['children'] ?? null) && array_is_list($node['children']) && count($node['children']) >= 1, 422, 'An expression needs at least one part.');
                return ['type' => $type, 'children' => array_map(fn ($n) => $validate($n, $depth + 1), $node['children'])];
            }
            $fields = ['fraction' => ['numerator', 'denominator'], 'power' => ['base', 'exponent'], 'root' => ['radicand', 'index']][$type] ?? null;
            abort_unless($fields, 422, 'Unsupported maths type.');
            $result = ['type' => $type];
            foreach ($fields as $field) $result[$field] = $type === 'root' && $field === 'index' && empty($node[$field]) ? null : $validate($node[$field] ?? null, $depth + 1);
            return $result;
        };
        return ['instructions' => $instructions, 'math' => $validate($math)];
    }
}
