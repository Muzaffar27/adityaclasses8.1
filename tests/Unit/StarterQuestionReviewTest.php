<?php

namespace Tests\Unit;

use App\Models\QuestionReview;
use App\Services\StarterQuestionReviewService;
use PHPUnit\Framework\TestCase;

class StarterQuestionReviewTest extends TestCase
{
    public function test_flags_consume_starter_review_slots_without_becoming_approved(): void
    {
        $questions = []; $reviews = collect();
        for ($i = 0; $i < 12; $i++) {
            $id = hash('sha256', "question:$i");
            $questions[$id] = ['id' => $id, 'grade_id' => 1, 'subject_id' => 1, 'content' => ['math' => ['type' => 'number', 'value' => (string) $i]],
                'origins' => [['lesson_id' => 10, 'topic' => 'Fractions']]];
            $reviews->put($id, new QuestionReview(['id' => $id, 'status' => 'pending']));
        }
        $service = new StarterQuestionReviewService;
        $initial = $service->select($questions, $reviews)['ids'];
        $reviews[$initial[0]]->status = 'needs_correction';
        $this->assertCount(4, $service->select($questions, $reviews)['ids']);
        foreach ($initial as $id) $reviews[$id]->status = 'needs_correction';
        $selection = $service->select($questions, $reviews);
        $this->assertSame([], $selection['ids']);
        $this->assertSame(7, $selection['library_pending']);
        $this->assertSame(5, $selection['flagged_for_goal']);
        $this->assertSame(0, $selection['approved_for_goal']);
        $this->assertSame(0, $selection['topics_ready']);
        $reviews[$initial[0]]->status = 'pending';
        $questions[$initial[0]]['correction_of'] = 'older-flagged-version';
        $this->assertSame([], $service->select($questions, $reviews)['ids']);
        $reviews[$initial[0]]->status = 'approved';
        $this->assertSame([], $service->select($questions, $reviews)['ids']);
    }

    public function test_selection_is_deterministic_diverse_and_separate_by_course(): void
    {
        $questions = []; $reviews = collect();
        foreach ([1, 2] as $grade) foreach ([10, 20] as $lesson) for ($i = 0; $i < 6; $i++) {
            $id = hash('sha256', "$grade:$lesson:$i");
            $questions[$id] = ['id' => $id, 'grade_id' => $grade, 'subject_id' => 1, 'content' => ['math' => ['type' => 'number', 'value' => (string) $i]],
                'origins' => [['lesson_id' => $lesson, 'topic' => 'Fractions']]];
            $reviews->push(new QuestionReview(['id' => $id, 'status' => 'pending']));
        }
        $service = new StarterQuestionReviewService;
        $selection = $service->select($questions, $reviews);
        $this->assertCount(10, $selection['ids']);
        $this->assertSame(2, $selection['topic_count']);
        $this->assertSame(10, $selection['goal_total']);
        $this->assertSame(0, $selection['approved_for_goal']);
        $this->assertSame($selection, $service->select(array_reverse($questions, true), $reviews->reverse()));
        foreach ([1, 2] as $grade) {
            $chosen = array_filter($selection['ids'], fn ($id) => $questions[$id]['grade_id'] === $grade);
            $this->assertCount(5, $chosen);
            $this->assertCount(2, array_unique(array_map(fn ($id) => $questions[$id]['origins'][0]['lesson_id'], $chosen)));
        }
        $reviews->each(fn ($r) => $r->status = in_array($r->id, $selection['ids'], true) ? 'approved' : 'needs_correction');
        $this->assertSame([], $service->select($questions, $reviews)['ids']);
        $progress = $service->select($questions, $reviews);
        $this->assertSame(10, $progress['approved_for_goal']);
        $this->assertSame(2, $progress['topics_ready']);
        $reviews->each(fn ($r) => $r->status = $questions[$r->id]['grade_id'] === 1 ? 'approved' : 'needs_correction');
        $progress = $service->select($questions, $reviews);
        $this->assertSame(5, $progress['approved_for_goal']);
        $this->assertSame(2, $progress['topic_count']);
        $this->assertSame(1, $progress['topics_ready']);
    }
}
