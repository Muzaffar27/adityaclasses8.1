<?php

namespace App\Services;

use InvalidArgumentException;

/** Pure blueprint allocation. The caller must supply source-validated questions. */
class TestPaperSelector
{
    public const VERSION = 'three-topics-15-v1';
    public const COUNT = 15;
    public const MINIMUM = 3;

    public function select(array $questions, int $grade, int $subject, array $topics, bool $randomize = true): array
    {
        if ($grade < 1 || $subject < 1 || count($topics) !== 3
            || array_filter($topics, fn ($t) => !is_string($t) || trim($t) === '')
            || count(array_unique($topics, SORT_STRING)) !== 3) {
            throw new InvalidArgumentException('Choose one grade, one subject and exactly three distinct topic names.');
        }
        $topics = array_values($topics);
        $pool = []; $candidates = array_fill(0, 3, []);
        foreach ($questions as $question) {
            if (($question['status'] ?? '') !== 'approved' || (int) $question['grade_id'] !== $grade
                || (int) $question['subject_id'] !== $subject || !preg_match('/^[a-f0-9]{64}$/', $question['id'] ?? '')) continue;
            $pool[$question['id']] = $question;
            foreach ($topics as $index => $topic) {
                if (in_array($topic, array_column($question['origins'] ?? [], 'topic'), true)) $candidates[$index][$question['id']] = true;
            }
        }
        $counts = array_map('count', $candidates);
        $union = array_unique(array_merge(...array_map('array_keys', $candidates)));
        $base = ['blueprint' => self::VERSION, 'grade_id' => $grade, 'subject_id' => $subject,
            'target_count' => self::COUNT, 'minimum_per_topic' => self::MINIMUM, 'available_unique' => count($union),
            'counting_unit' => 'one_canonical_question_including_subparts', 'workload_review_required' => true,
            'topics' => array_map(fn ($i) => ['topic' => $topics[$i], 'available' => $counts[$i],
                'minimum_shortage' => max(0, self::MINIMUM - $counts[$i])], range(0, 2))];
        if (min($counts) < self::MINIMUM || count($union) < self::COUNT) {
            return $base + ['ready' => false, 'reason' => 'insufficient_pool',
                'additional_unique_needed' => max(0, self::COUNT - count($union)), 'questions' => []];
        }
        foreach ($candidates as &$ids) {
            $ids = array_keys($ids); sort($ids);
            if ($randomize) $ids = $this->randomOrder($ids);
        }
        unset($ids);
        // Try the fairest feasible quota first; matching can move shared questions to another topic.
        $quotas = [];
        for ($a = 3; $a <= min(9, $counts[0]); $a++) {
            for ($b = 3; $b <= min(9, $counts[1]); $b++) {
                $c = self::COUNT - $a - $b;
                if ($c >= 3 && $c <= $counts[2]) $quotas[] = [$a, $b, $c];
            }
        }
        if ($randomize) $quotas = $this->randomOrder($quotas);
        usort($quotas, fn ($a, $b) => array_sum(array_map(fn ($n) => ($n - 5) ** 2, $a))
            <=> array_sum(array_map(fn ($n) => ($n - 5) ** 2, $b)));
        foreach ($quotas as $quota) {
            $slots = [];
            foreach ($quota as $index => $number) for ($i = 0; $i < $number; $i++) $slots[] = $index;
            $owners = [];
            $assign = function (int $slot, array &$seen) use (&$assign, &$owners, $slots, $candidates): bool {
                foreach ($candidates[$slots[$slot]] as $id) {
                    if (isset($seen[$id])) continue;
                    $seen[$id] = true;
                    if (!isset($owners[$id]) || $assign($owners[$id], $seen)) {
                        $owners[$id] = $slot;
                        return true;
                    }
                }
                return false;
            };
            foreach (array_keys($slots) as $slot) {
                $seen = [];
                if (!$assign($slot, $seen)) continue 2;
            }
            asort($owners);
            $selected = [];
            foreach ($owners as $id => $slot) {
                $q = $pool[$id];
                // Explicit allowlist: future answer keys/rubrics must never leak into a paper.
                $selected[] = ['id' => $id, 'topic' => $topics[$slots[$slot]],
                    'content' => array_intersect_key($q['content'], array_flip(['instructions', 'text', 'math', 'diagrams'])),
                    'origins' => array_values(array_filter($q['origins'], fn ($o) => $o['topic'] === $topics[$slots[$slot]]))];
            }
            foreach ($base['topics'] as $i => &$topic) $topic['selected'] = $quota[$i];
            unset($topic);
            return $base + ['ready' => true, 'count' => count($selected), 'questions' => $selected];
        }
        // A shared subset can block quotas even when each count and the total look sufficient.
        $conflicts = [];
        foreach ([[0, 1], [0, 2], [1, 2]] as $indices) {
            $unique = count(array_unique(array_merge(...array_map(fn ($i) => $candidates[$i], $indices))));
            if ($unique < 6) $conflicts[] = ['topics' => array_map(fn ($i) => $topics[$i], $indices),
                'available_unique' => $unique, 'additional_unique_needed' => 6 - $unique];
        }
        return $base + ['ready' => false, 'reason' => 'overlapping_topic_shortage', 'conflicts' => $conflicts, 'questions' => []];
    }

    private function randomOrder(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }
}
