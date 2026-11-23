<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\QuestionReview;
use App\Models\Subject;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TestPaperReadinessService
{
    public const MISSING_METADATA = ['answer_key', 'marks', 'skills', 'marking_method'];

    public function __construct(private QuestionReviewService $reviews, private TestPaperSelector $selector, private QuestionAnswerKeyService $answerKeys) {}

    public function snapshot(): array
    {
        foreach (['question_reviews', 'lesson_question_sources', 'lessons'] as $table) {
            if (!Schema::hasTable($table)) throw new RuntimeException("Required table {$table} is unavailable; readiness cannot be established.");
        }
        // Read-only: synchronize() changes review records and must not be used here.
        $current = $this->reviews->currentQuestions();
        $approvedRows = QuestionReview::where('status', 'approved')->get(['id', 'grade_id', 'subject_id']);
        $approved = [];
        foreach ($approvedRows as $row) {
            $q = $current[$row->id] ?? null;
            if ($q && (int) $q['grade_id'] === (int) $row->grade_id && (int) $q['subject_id'] === (int) $row->subject_id) {
                $approved[$row->id] = $q + ['status' => 'approved'];
            }
        }
        $grades = Grade::pluck('name', 'id'); $subjects = Subject::pluck('name', 'id');
        $groups = [];
        foreach (Lesson::where('is_active', true)->whereNotNull('topic')->orderBy('grade_id')->orderBy('subject_id')->orderBy('topic')->get() as $lesson) {
            if (trim($lesson->topic) === '') continue;
            $key = json_encode([$lesson->grade_id, $lesson->subject_id, $lesson->topic]);
            if (!isset($groups[$key])) $groups[$key] = ['grade_id' => $lesson->grade_id, 'grade' => $grades[$lesson->grade_id] ?? (string) $lesson->grade_id,
                'subject_id' => $lesson->subject_id, 'subject' => $subjects[$lesson->subject_id] ?? (string) $lesson->subject_id,
                'topic' => $lesson->topic, 'current_ids' => [], 'approved_ids' => [], 'lessons' => []];
            $groups[$key]['lessons'][$lesson->id] = ['id' => $lesson->id, 'title' => $lesson->title, 'approved_ids' => []];
        }
        foreach ($current as $q) foreach ($q['origins'] as $origin) {
            $key = json_encode([(int) $q['grade_id'], (int) $q['subject_id'], $origin['topic']]);
            if (!isset($groups[$key])) continue;
            $groups[$key]['current_ids'][$q['id']] = true;
            if (isset($approved[$q['id']])) {
                $groups[$key]['approved_ids'][$q['id']] = true;
                $groups[$key]['lessons'][$origin['lesson_id']]['approved_ids'][$q['id']] = true;
            }
        }
        $keys = $this->answerKeys->approvedFor($approved);
        foreach ($groups as &$group) {
            $group['current_count'] = count($group['current_ids']);
            $group['approved_count'] = count($group['approved_ids']);
            $group['needed_for_minimum'] = max(0, 3 - $group['approved_count']);
            $group['needed_for_five'] = max(0, 5 - $group['approved_count']);
            $group['missing_metadata'] = array_fill_keys(self::MISSING_METADATA, $group['approved_count']);
            $group['approved_answer_keys'] = count(array_intersect(array_keys($group['approved_ids']), array_keys($keys)));
            foreach (['answer_key', 'marks', 'marking_method'] as $field) $group['missing_metadata'][$field] -= $group['approved_answer_keys'];
            unset($group['current_ids'], $group['approved_ids']);
            foreach ($group['lessons'] as &$lesson) {
                $lesson['approved_count'] = count($lesson['approved_ids']); unset($lesson['approved_ids']);
            }
            unset($lesson);
            $group['lessons'] = array_values($group['lessons']);
        }
        unset($group);
        return ['report' => ['blueprint' => TestPaperSelector::VERSION, 'current_unique' => count($current),
            'approved_rows' => $approvedRows->count(), 'current_approved_unique' => count($approved),
            'historical_or_unusable_approvals' => $approvedRows->count() - count($approved),
            'marking_ready' => count($approved) > 0 && count($keys) === count($approved), 'approved_answer_keys' => count($keys),
            'metadata_status' => count($keys).' of '.count($approved).' usable questions have an approved answer key/marks/method. Skills still need classification; content approval is separate.',
            'topics' => array_values($groups)], 'approved' => array_values($approved)];
    }

    public function preview(int $grade, int $subject, array $topics, array $excludedIds = []): array
    {
        $snapshot = $this->snapshot();
        $excluded = array_fill_keys($excludedIds, true);
        $pool = array_filter($snapshot['approved'], fn ($q) => !isset($excluded[$q['id']]));
        return $this->selector->select($pool, $grade, $subject, $topics)
            + ['purpose' => 'approved_bank_preview', 'saved_attempt' => false, 'marking_ready' => false,
                'excluded_previously_issued' => count($excluded),
                'grade' => Grade::find($grade)?->name, 'subject' => Subject::find($subject)?->name];
    }
}
