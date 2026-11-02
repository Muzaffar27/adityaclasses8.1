<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StarterQuestionReviewService
{
    public const TARGET = 5;

    public function select(array $current, Collection $reviews): array
    {
        $reviews = $reviews->keyBy('id');
        $groups = []; $pending = []; $formatKeys = [];
        foreach ($current as $id => $question) {
            $status = $reviews->get($id)?->status;
            if ($status === null) continue;
            $formatKeys[$id] = $this->format($question['content']);
            if (in_array($status, ['pending', 'review_again'], true)) $pending[$id] = true;
            foreach ($question['origins'] as $origin) {
                $key = $question['grade_id'] . ':' . $question['subject_id'] . ':' . $origin['topic'];
                $groups[$key][$id] = $question;
            }
        }
        ksort($groups);
        $selected = []; $topics = [];
        foreach ($groups as $key => $questions) {
            [$grade, $subject, $topic] = explode(':', $key, 3);
            $approved = count(array_filter(array_keys($questions), fn ($id) => $reviews[$id]->status === 'approved'));
            $needsCorrection = fn ($id) => $reviews[$id]->status === 'needs_correction'
                || (isset($questions[$id]['correction_of']) && in_array($reviews[$id]->status, ['pending', 'review_again'], true));
            $flagged = count(array_filter(array_keys($questions), $needsCorrection));
            $topics[] = ['key' => $key, 'grade_id' => (int) $grade, 'subject_id' => (int) $subject, 'topic' => $topic,
                'approved' => $approved, 'flagged' => $flagged, 'target' => self::TARGET, 'ready' => $approved >= self::TARGET];
            $covered = []; $lessons = []; $formats = [];
            $record = function ($id, $question) use (&$covered, &$lessons, &$formats, $formatKeys) {
                $covered[$id] = true;
                foreach (array_unique(array_column($question['origins'], 'lesson_id')) as $lesson) $lessons[$lesson] = ($lessons[$lesson] ?? 0) + 1;
                $format = $formatKeys[$id];
                $formats[$format] = ($formats[$format] ?? 0) + 1;
            };
            // A flag consumes a review slot; fix that version instead of endlessly replacing it.
            foreach ($questions as $id => $question) if ($reviews[$id]->status === 'approved' || $needsCorrection($id) || isset($selected[$id])) $record($id, $question);
            while (count($covered) < self::TARGET) {
                $candidates = array_filter($questions, fn ($q) => isset($pending[$q['id']]) && !isset($covered[$q['id']]));
                if (!$candidates) break;
                uasort($candidates, function ($a, $b) use ($lessons, $formats, $formatKeys) {
                    $score = fn ($q) => [min(array_map(fn ($id) => $lessons[$id] ?? 0, array_column($q['origins'], 'lesson_id'))),
                        $formats[$formatKeys[$q['id']]] ?? 0, $q['id']];
                    return $score($a) <=> $score($b);
                });
                $question = reset($candidates);
                $selected[$question['id']] = true;
                $record($question['id'], $question);
            }
        }
        return ['ids' => array_keys($selected), 'target_per_topic' => self::TARGET, 'topic_count' => count($groups),
            'remaining' => count($selected), 'library_pending' => count($pending), 'topics' => $topics,
            'goal_total' => count($topics) * self::TARGET,
            'approved_for_goal' => array_sum(array_map(fn ($topic) => min($topic['approved'], self::TARGET), $topics)),
            'flagged_for_goal' => array_sum(array_map(fn ($topic) => min($topic['flagged'], max(0, self::TARGET - $topic['approved'])), $topics)),
            'topics_ready' => count(array_filter($topics, fn ($topic) => $topic['ready']))];
    }

    private function format(array $content): string
    {
        $shape = function ($value) use (&$shape) {
            if (!is_array($value)) return $value;
            if (($value['type'] ?? null) === 'number') return ['type' => 'number', 'value' => '#'];
            return array_map(fn ($part) => is_array($part) ? $shape($part) : $part, $value);
        };
        return hash('sha256', json_encode([preg_replace('/\s+/', ' ', mb_strtolower($content['instructions'] ?? '')),
            isset($content['math']) ? $shape($content['math']) : 'text', !empty($content['diagrams'])]));
    }
}
