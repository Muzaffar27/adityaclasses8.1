<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class QuestionReviewService
{
    public function currentQuestions(): array
    {
        if (!Schema::hasTable('question_reviews')) return [];
        $path = Storage::disk('local')->path('structured/bank.json');
        if (!is_file($path)) return [];
        $bank = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $sources = LessonQuestionSource::whereIn('status', ['processed', 'processed_with_issues'])->whereNotNull('source_sha256')->get();
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
        $current = [];
        foreach ($bank['questions'] ?? [] as $question) {
            if (empty($question['active']) || !preg_match('/^[a-f0-9]{64}$/', $question['id'] ?? '')) continue;
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
                'content' => array_intersect_key($question, array_flip(['instructions', 'text', 'math'])), 'origins' => $origins];
        }
        return $current;
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

    public function approvedQuestions(int $grade, int $subject): array
    {
        if (!Schema::hasTable('question_reviews')) return [];
        $current = $this->currentQuestions();
        $approved = QuestionReview::where('grade_id', $grade)->where('subject_id', $subject)->where('status', 'approved')->pluck('id')->all();
        return array_values(array_filter($current, fn ($q) => in_array($q['id'], $approved, true)));
    }
}
