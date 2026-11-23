<?php

namespace Tests\Unit;

use App\Services\TestPaperSelector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TestPaperSelectorTest extends TestCase
{
    private function question(string $key, array $topics, array $extra = []): array
    {
        return $extra + ['id' => hash('sha256', $key), 'grade_id' => 1, 'subject_id' => 2, 'status' => 'approved',
            'content' => ['text' => $key, 'answer_key' => 'private'],
            'origins' => array_map(fn ($topic) => ['topic' => $topic, 'lesson_id' => 10], $topics)];
    }

    private function pools(array $counts): array
    {
        $questions = [];
        foreach ($counts as $topic => $count) for ($i = 0; $i < $count; $i++) $questions[] = $this->question("$topic:$i", [$topic]);
        return $questions;
    }

    private function select(array $questions): array
    {
        return (new TestPaperSelector)->select($questions, 1, 2, ['A', 'B', 'C'], false);
    }

    public function test_balanced_unique_paper_preserves_content_but_never_answer_keys(): void
    {
        $pool = $this->pools(['A' => 8, 'B' => 8, 'C' => 8]);
        $paper = $this->select($pool);
        $this->assertTrue($paper['ready']);
        $this->assertSame([5, 5, 5], array_column($paper['topics'], 'selected'));
        $this->assertCount(15, array_unique(array_column($paper['questions'], 'id')));
        $this->assertSame($paper, $this->select(array_reverse($pool)));
        foreach ($paper['questions'] as $q) $this->assertArrayNotHasKey('answer_key', $q['content']);
    }

    public function test_redistribution_keeps_three_per_topic_and_fills_fifteen(): void
    {
        $paper = $this->select($this->pools(['A' => 3, 'B' => 7, 'C' => 8]));
        $this->assertTrue($paper['ready']);
        $this->assertSame([3, 6, 6], array_column($paper['topics'], 'selected'));
        $paper = $this->select($this->pools(['A' => 4, 'B' => 4, 'C' => 7]));
        $this->assertSame([4, 4, 7], array_column($paper['topics'], 'selected'));
    }

    public function test_shared_questions_are_reassigned_instead_of_falsely_reporting_a_shortage(): void
    {
        $questions = $this->pools(['A' => 5, 'C' => 5]);
        for ($i = 0; $i < 5; $i++) $questions[] = $this->question("shared:$i", ['A', 'B']);
        $paper = $this->select($questions);
        $this->assertTrue($paper['ready']);
        $this->assertSame([5, 5, 5], array_column($paper['topics'], 'selected'));
        $this->assertCount(15, array_unique(array_column($paper['questions'], 'id')));
    }

    public function test_minimum_shortage_is_not_hidden_by_a_large_other_pool(): void
    {
        $paper = $this->select($this->pools(['A' => 2, 'B' => 30, 'C' => 30]));
        $this->assertFalse($paper['ready']);
        $this->assertSame(1, $paper['topics'][0]['minimum_shortage']);
        $this->assertSame([], $paper['questions']);
    }

    public function test_overlapping_counts_do_not_satisfy_the_distinct_total(): void
    {
        $questions = [];
        for ($i = 0; $i < 5; $i++) $questions[] = $this->question("shared:$i", ['A', 'B', 'C']);
        $paper = $this->select($questions);
        $this->assertFalse($paper['ready']);
        $this->assertSame(10, $paper['additional_unique_needed']);
    }

    public function test_shared_subset_shortage_is_reported_even_with_fifteen_distinct_questions(): void
    {
        $questions = $this->pools(['C' => 10]);
        for ($i = 0; $i < 5; $i++) $questions[] = $this->question("shared:$i", ['A', 'B']);
        $paper = $this->select($questions);
        $this->assertFalse($paper['ready']);
        $this->assertSame('overlapping_topic_shortage', $paper['reason']);
        $this->assertSame(1, $paper['conflicts'][0]['additional_unique_needed']);
    }

    public function test_wrong_course_pending_and_unrelated_questions_are_excluded(): void
    {
        $questions = $this->pools(['A' => 5, 'B' => 5, 'C' => 4]);
        $questions[] = $this->question('foreign-grade', ['C'], ['grade_id' => 3]);
        $questions[] = $this->question('foreign-subject', ['C'], ['subject_id' => 3]);
        $questions[] = $this->question('pending', ['C'], ['status' => 'pending']);
        $questions[] = $this->question('other', ['D']);
        $questions[] = $questions[0];
        $paper = $this->select($questions);
        $this->assertFalse($paper['ready']);
        $this->assertSame(14, $paper['available_unique']);
    }

    public function test_randomized_selections_always_preserve_constraints(): void
    {
        $pool = $this->pools(['A' => 12, 'B' => 12, 'C' => 12]);
        for ($i = 0; $i < 10; $i++) {
            $paper = (new TestPaperSelector)->select($pool, 1, 2, ['A', 'B', 'C']);
            $this->assertTrue($paper['ready']);
            $this->assertCount(15, array_unique(array_column($paper['questions'], 'id')));
            $this->assertSame([5, 5, 5], array_column($paper['topics'], 'selected'));
        }
    }

    public function test_duplicate_topics_are_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TestPaperSelector)->select([], 1, 2, ['A', 'A', 'C']);
    }
}
