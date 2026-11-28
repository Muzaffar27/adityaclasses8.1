<?php

namespace App\Services;

use App\Models\{TestPaper, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TestPaperResponseService
{
    public function __construct(private SavedTestPaperService $papers) {}

    private function authorize(User $actor, string $id): void
    {
        $paper = $this->papers->findFor($actor, $id);
        abort_unless((int) $paper->batch->student_id === $actor->id, 403);
        foreach (['test_paper_answers', 'test_paper_response_versions'] as $table) abort_unless(Schema::hasTable($table), 503, 'Answer entry setup is pending.');
    }

    public function save(User $actor, string $id, int $revision, array $answers): TestPaper
    {
        $this->authorize($actor, $id);
        $handwritingReady = Schema::hasColumn('test_paper_answers', 'answer_ink') && Schema::hasColumn('test_paper_answers', 'working_ink');
        foreach ($answers as $answer) {
            abort_unless(empty($answer['answer_ink']), 422, 'Enter final answers using the maths keypad. Handwriting is available for working.');
            if (array_key_exists('answer_ink', $answer) || array_key_exists('working_ink', $answer)) {
                abort_unless($handwritingReady, 503, 'Handwriting setup is pending.');
            }
        }
        return DB::transaction(function () use ($actor, $id, $revision, $answers, $handwritingReady) {
            $paper = TestPaper::with(['batch', 'attempt', 'questions:id,paper_id,position'])->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($paper->status === 'started', 409, 'This paper is not open for editing.');
            $current = (int) (DB::table('test_paper_response_versions')->where('attempt_id', $paper->attempt->id)->value('revision') ?? 0);
            abort_unless($current === $revision, 409, 'Another tab saved newer answers. Keep your draft and reload the saved version before retrying.');
            $questions = $paper->questions->keyBy('position');
            $existing = $handwritingReady ? DB::table('test_paper_answers')->where('attempt_id', $paper->attempt->id)
                ->get(['question_id', 'answer_ink', 'working_ink'])->keyBy('question_id') : collect();
            $rows = [];
            foreach ($answers as $answer) {
                $question = $questions->get($answer['number']);
                abort_unless($question, 422, 'Question does not belong to this paper.');
                $row = ['attempt_id' => $paper->attempt->id, 'question_id' => $question->id,
                    'answer' => $answer['answer'] ?? '', 'working' => $answer['working'] ?? '', 'created_at' => now(), 'updated_at' => now()];
                if ($handwritingReady) {
                    foreach (['answer_ink', 'working_ink'] as $field) {
                        $row[$field] = array_key_exists($field, $answer)
                            ? (empty($answer[$field]['strokes']) ? null : json_encode($answer[$field], JSON_THROW_ON_ERROR))
                            : ($existing->get($question->id)?->$field);
                    }
                }
                $rows[] = $row;
            }
            $updates = ['answer', 'working', 'updated_at'];
            if ($handwritingReady) $updates = array_merge($updates, ['answer_ink', 'working_ink']);
            DB::table('test_paper_answers')->upsert($rows, ['attempt_id', 'question_id'], $updates);
            DB::table('test_paper_response_versions')->upsert([['attempt_id' => $paper->attempt->id,
                'revision' => $current + 1, 'created_at' => now(), 'updated_at' => now()]], ['attempt_id'], ['revision', 'updated_at']);
            return $paper;
        }, 3);
    }

    public function submit(User $actor, string $id, int $revision): TestPaper
    {
        $this->authorize($actor, $id);
        return DB::transaction(function () use ($actor, $id, $revision) {
            $paper = TestPaper::with(['batch', 'attempt'])->whereKey($id)->lockForUpdate()->firstOrFail();
            if (in_array($paper->status, ['submitted', 'awaiting_review', 'marked'], true)) return $paper;
            abort_unless($paper->status === 'started', 409, 'Start the paper before submitting.');
            $current = (int) (DB::table('test_paper_response_versions')->where('attempt_id', $paper->attempt->id)->value('revision') ?? 0);
            abort_unless($current === $revision, 409, 'Newer answers were saved in another tab. Reload before submitting.');
            $savedAnswers = DB::table('test_paper_answers')->where('attempt_id', $paper->attempt->id)
                ->pluck('answer', 'question_id');
            $missing = [];
            foreach ($this->papers->questionsInDisplayOrder($paper) as $index => $question) {
                if (trim($savedAnswers->get($question->id) ?? '') === '') {
                    $missing[] = $index + 1;
                }
            }
            if ($missing) {
                throw ValidationException::withMessages([
                    'answers' => 'Final answers missing for questions '.implode(', ', $missing).'.',
                ]);
            }
            return $this->papers->submitForReview($paper, $actor);
        }, 3);
    }
}
