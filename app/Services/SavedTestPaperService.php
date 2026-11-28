<?php

namespace App\Services;

use App\Models\AssessmentBatch;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\QuestionAnswerKey;
use App\Models\TestPaper;
use App\Models\TestPaperAttempt;
use App\Models\TestPaperEvent;
use App\Models\TestPaperQuestion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SavedTestPaperService
{
    public function __construct(private TestPaperReadinessService $readiness, private QuestionAnswerKeyService $answerKeys) {}

    public function ensureReady(): void
    {
        foreach (['assessment_batches', 'test_papers', 'test_paper_questions', 'test_paper_assets', 'test_paper_attempts', 'test_paper_events'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Saved test papers are not ready. Apply the saved-paper migration.');
        }
    }

    public function staff(User $actor): void
    {
        abort_unless(in_array($actor->role, ['tutor', 'admin'], true), 403);
    }

    public function findFor(User $actor, string $id): TestPaper
    {
        $this->ensureReady();
        abort_unless(in_array($actor->role, ['student', 'tutor', 'admin'], true), 403);
        $paper = TestPaper::with('batch')->findOrFail($id);
        abort_unless($actor->role !== 'student' || (int) $paper->batch->student_id === $actor->id, 404);
        return $paper;
    }

    public function assign(User $actor, array $input, array $excludedIds = []): TestPaper
    {
        $this->staff($actor); $this->ensureReady();
        return $this->assignInternal($actor, $input, $excludedIds);
    }

    /** Internal automatic workflow; never accepts another learner's identity. */
    public function assignAutomatic(User $learner, array $input, array $excludedIds = []): TestPaper
    {
        $this->ensureReady();
        abort_unless((int) $input['student_id'] === $learner->id && in_array($learner->role, ['student', 'tutor', 'admin'], true), 403);
        return $this->assignInternal($learner, $input, $excludedIds);
    }

    private function assignInternal(User $actor, array $input, array $excludedIds): TestPaper
    {
        return DB::transaction(function () use ($actor, $input, $excludedIds) {
            // Serialise requests for one student, including first creation when no batch row exists.
            $student = User::whereKey($input['student_id'])->lockForUpdate()->firstOrFail();
            abort_unless(in_array($student->role, ['student', 'tutor', 'admin'], true), 422, 'Choose a learning account.');
            $scope = array_intersect_key($input, array_flip(['student_id', 'grade_id', 'subject_id', 'purpose', 'batch_key']));
            $batch = AssessmentBatch::where($scope)->lockForUpdate()->first();
            if ($batch) {
                abort_unless($batch->topics === $input['topics'], 409, 'This batch key already belongs to a different topic selection.');
                // Return the original version even after cancellation/replacement; never silently regenerate.
                return $batch->papers()->where('version', 1)->firstOrFail();
            }
            $batch = AssessmentBatch::create($scope + ['id' => (string) Str::uuid(), 'topics' => $input['topics']]);
            return $this->issue($actor, $batch, $input['batch_key'], null, $excludedIds);
        }, 3);
    }

    private function issue(User $actor, AssessmentBatch $batch, string $creationKey, ?TestPaper $previous = null, array $excludedIds = []): TestPaper
    {
        [$lessons, $sources, $reviews] = $this->lockCourseForGeneration($batch->grade_id, $batch->subject_id);
        // The automatic reconciler also acquires these locks before its first consistent read.
        $this->courseAccess($batch->student_id, $batch->grade_id, $batch->subject_id);
        $selected = $this->readiness->preview($batch->grade_id, $batch->subject_id, $batch->topics, $excludedIds);
        if (!$selected['ready']) {
            throw ValidationException::withMessages(['topics' => ['No paper saved: '.json_encode(array_diff_key($selected, ['questions' => true]), JSON_UNESCAPED_UNICODE)]]);
        }
        $keys = $this->answerKeys->approvedFor(array_column($selected['questions'], null, 'id'));
        $markingReady = count($keys) === count($selected['questions']);
        $paper = TestPaper::create(['id' => (string) Str::uuid(), 'batch_id' => $batch->id,
            'version' => $previous ? $previous->version + 1 : 1, 'creation_key' => $creationKey,
            'previous_paper_id' => $previous?->id, 'created_by' => $actor->id, 'blueprint' => $selected['blueprint'],
            'status' => 'generated', 'grade_name' => $selected['grade'], 'subject_name' => $selected['subject'], 'marking_ready' => $markingReady]);
        $usedSources = [];
        foreach ($selected['questions'] as $position => $question) {
            $review = $reviews->get($question['id']);
            abort_unless($review && $review->status === 'approved', 409, 'A selected approval changed. Retry generation.');
            $originSnapshots = [];
            foreach ($question['origins'] as $origin) {
                $source = $sources->get($origin['source_id']);
                abort_unless($source && in_array($source->status, ['processed', 'processed_with_issues'], true), 409, 'A source changed. Retry generation.');
                $lesson = $lessons->get($origin['lesson_id']);
                $field = LessonQuestionBankService::FIELDS[$source->slot];
                abort_unless($lesson && $lesson->is_active && (int) $source->lesson_id === (int) $lesson->id
                    && $source->topic === $origin['topic'] && $lesson->topic === $origin['topic']
                    && $lesson->{$field} === $source->pdf_path, 409, 'A topic binding changed. Retry generation.');
                $usedSources[$source->id] = $source;
                $originSnapshots[] = $origin + ['source_revision' => $source->revision, 'source_sha256' => $source->source_sha256,
                    'processor_version' => $source->processor_version];
            }
            $content = $question['content'];
            foreach ($content['diagrams'] ?? [] as $diagram) $this->retainDiagram($diagram);
            $key = $keys[$question['id']] ?? null;
            TestPaperQuestion::create(['paper_id' => $paper->id, 'position' => $position + 1,
                'source_question_id' => $question['id'], 'topic' => $question['topic'], 'content' => $content,
                'provenance' => ['origins' => $originSnapshots, 'approval_revision' => $review->revision,
                    'approved_at' => $review->approved_at?->toISOString()], 'marks' => $key?->answer['marks'],
                'marking_snapshot' => $key ? ['answer_key_id' => $key->id, 'version' => $key->version,
                    'question_content_hash' => $key->question_content_hash, 'answer' => $key->answer,
                    'approved_by' => $key->approved_by, 'approved_at' => $key->approved_at?->toISOString()] : null]);
        }
        foreach ($usedSources as $source) {
            $file = Storage::disk('local')->path($source->pdf_path);
            abort_unless(is_file($file) && is_readable($file) && hash_equals($source->source_sha256, hash_file('sha256', $file)),
                409, 'A PDF changed during generation. Retry generation.');
        }
        $total = $markingReady ? array_sum(array_map(fn ($k) => (int) round((float) $k->answer['marks'] * 100), $keys)) / 100 : null;
        TestPaperAttempt::create(['id' => (string) Str::uuid(), 'paper_id' => $paper->id, 'total_marks' => $total]);
        $this->event($paper, $actor, null, 'generated', $previous ? 'Replacement version' : null);
        $this->transition($paper, $actor, 'available');
        return $paper;
    }

    /** Internal transaction boundary shared by manual and automatic generation. */
    public function lockCourseForGeneration(int $grade, int $subject): array
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Course locks require a transaction.');
        // Lock the source/approval domain before taking a consistent selection snapshot.
        $lessons = Lesson::where('grade_id', $grade)->where('subject_id', $subject)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $sources = LessonQuestionSource::where('grade_id', $grade)->where('subject_id', $subject)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        // Uses the existing (status, grade_id, subject_id) index rather than locking a full-bank scan.
        $reviews = QuestionReview::where('status', 'approved')->where('grade_id', $grade)->where('subject_id', $subject)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if (Schema::hasTable('question_answer_keys')) QuestionAnswerKey::whereIn('question_id', $reviews->keys())
            ->orderBy('question_id')->orderBy('version')->lockForUpdate()->get();
        return [$lessons, $sources, $reviews];
    }

    private function retainDiagram(array $diagram): void
    {
        $hash = $diagram['sha256'] ?? '';
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $hash) && ($diagram['path'] ?? '') === "structured/diagrams/$hash.png", 409, 'Invalid question diagram.');
        $path = Storage::disk('local')->path($diagram['path']);
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        abort_unless(is_string($bytes) && hash_equals($hash, hash('sha256', $bytes)), 409, 'A question diagram changed. Retry generation.');
        DB::table('test_paper_assets')->insertOrIgnore(['sha256' => $hash, 'base64_bytes' => base64_encode($bytes)]);
        $stored = base64_decode(DB::table('test_paper_assets')->where('sha256', $hash)->value('base64_bytes'), true);
        abort_unless(is_string($stored) && hash_equals($hash, hash('sha256', $stored)), 409, 'The retained diagram failed its integrity check.');
    }

    public function start(User $actor, string $id): TestPaper
    {
        $record = $this->findFor($actor, $id);
        abort_unless((int) $record->batch->student_id === $actor->id, 403);
        return DB::transaction(function () use ($actor, $id) {
            $paper = TestPaper::with('batch')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->courseAccess($actor->id, $paper->batch->grade_id, $paper->batch->subject_id);
            if ($paper->status === 'started') return $paper;
            $this->transition($paper, $actor, 'started');
            $paper->attempt()->update(['started_at' => now()]);
            return $paper;
        }, 3);
    }

    public function cancel(User $actor, string $id, string $reason): TestPaper
    {
        $this->staff($actor); $this->findFor($actor, $id);
        return DB::transaction(function () use ($actor, $id, $reason) {
            $paper = TestPaper::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($paper->status === 'cancelled') return $paper;
            $this->transition($paper, $actor, 'cancelled', $reason);
            return $paper;
        }, 3);
    }

    public function replace(User $actor, string $id, string $key, string $reason): TestPaper
    {
        $this->staff($actor);
        $original = $this->findFor($actor, $id);
        return DB::transaction(function () use ($actor, $original, $key, $reason) {
            User::whereKey($original->batch->student_id)->lockForUpdate()->firstOrFail();
            $batch = AssessmentBatch::whereKey($original->batch_id)->lockForUpdate()->firstOrFail();
            $existing = $batch->papers()->where('creation_key', $key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->previous_paper_id === $original->id, 409, 'This replacement key belongs to another request.');
                return $existing;
            }
            $paper = $batch->papers()->orderByDesc('version')->lockForUpdate()->firstOrFail();
            abort_unless($paper->id === $original->id, 409, 'This paper has already been replaced.');
            if ($paper->status !== 'cancelled') $this->transition($paper, $actor, 'cancelled', $reason);
            $replacement = $this->issue($actor, $batch, $key, $paper);
            // Keep an explicit link/reason in the old paper audit trail even after prior cancellation.
            $this->event($paper, $actor, 'cancelled', 'cancelled', 'Replaced by '.$replacement->id.': '.$reason);
            return $replacement;
        }, 3);
    }

    private function courseAccess(int $student, int $grade, int $subject): void
    {
        if (User::whereKey($student)->whereIn('role', ['tutor', 'admin'])->exists()) return;
        abort_unless(LessonAccess::where('user_id', $student)->where('grade_id', $grade)->where('subject_id', $subject)->active()->exists(),
            403, 'The student needs active access to this grade and subject.');
    }

    public function submitForReview(TestPaper $paper, User $actor): TestPaper
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Submission requires a transaction.');
        abort_unless((int) $paper->batch->student_id === $actor->id, 403);
        $this->transition($paper, $actor, 'submitted');
        $paper->attempt()->update(['submitted_at' => now(), 'duration_seconds' => $paper->attempt->started_at ? max(0, now()->diffInSeconds($paper->attempt->started_at)) : null]);
        $this->transition($paper, $actor, 'awaiting_review');
        $paper->unsetRelation('attempt');
        return $paper;
    }

    private function transition(TestPaper $paper, User $actor, string $to, ?string $reason = null): void
    {
        $from = $paper->status;
        abort_unless(in_array($to, TestPaper::TRANSITIONS[$from] ?? [], true), 409, "Cannot change paper from $from to $to.");
        abort_unless(in_array($to, ['available', 'started', 'cancelled', 'submitted', 'awaiting_review'], true), 409, 'Marking is not enabled.');
        $paper->status = $to;
        if ($to === 'available') $paper->available_at = now();
        if ($to === 'cancelled') $paper->cancelled_at = now();
        $paper->save();
        $this->event($paper, $actor, $from, $to, $reason);
    }

    private function event(TestPaper $paper, User $actor, ?string $from, string $to, ?string $reason): void
    {
        TestPaperEvent::create(['paper_id' => $paper->id, 'actor_id' => $actor->id, 'from_status' => $from,
            'to_status' => $to, 'reason' => $reason, 'created_at' => now()]);
    }

    public function payload(TestPaper $paper, User $actor, bool $questions = true): array
    {
        $paper->loadMissing(['batch', 'attempt']);
        $own = (int) $paper->batch->student_id === $actor->id;
        $handwritingReady = Schema::hasColumn('test_paper_answers', 'answer_ink') && Schema::hasColumn('test_paper_answers', 'working_ink');
        $result = ['id' => $paper->id, 'version' => $paper->version, 'previous_paper_id' => $paper->previous_paper_id,
            'handwriting_available' => $handwritingReady && $own && in_array($paper->status, ['available', 'started'], true),
            'grade' => $paper->grade_name, 'subject' => $paper->subject_name, 'grade_id' => $paper->batch->grade_id,
            'subject_id' => $paper->batch->subject_id, 'purpose' => $paper->batch->purpose, 'status' => $paper->status,
            'marking_ready' => $paper->marking_ready, 'answering_available' => $own
                && Schema::hasTable('test_paper_answers') && in_array($paper->status, ['available', 'started'], true), 'available_at' => $paper->available_at,
            'attempt' => ['id' => $paper->attempt->id, 'started_at' => $paper->attempt->started_at,
                'submitted_at' => $paper->attempt->submitted_at, 'marked_at' => $paper->attempt->marked_at]];
        if ($questions) $result['questions'] = $this->questionsInDisplayOrder($paper)->map(function ($q) use ($paper, $actor) {
            $content = array_intersect_key($q->content, array_flip(['instructions', 'text', 'math', 'diagrams']));
            if (isset($content['diagrams'])) $content['diagrams'] = array_map(fn ($d) => [
                'url' => "/api/test-papers/{$paper->id}/diagrams/{$d['sha256']}",
                'width' => $d['width'] ?? null, 'height' => $d['height'] ?? null], $content['diagrams']);
            $item = ['number' => $q->position, 'content' => $content];
            if ($actor->role !== 'student') $item += ['source_question_id' => $q->source_question_id, 'topic' => $q->topic, 'provenance' => $q->provenance];
            return $item;
        })->all();
        if ($questions && Schema::hasTable('test_paper_answers') && Schema::hasTable('test_paper_response_versions')) {
            $result['responses'] = ['revision' => (int) (DB::table('test_paper_response_versions')->where('attempt_id', $paper->attempt->id)->value('revision') ?? 0),
                'answers' => DB::table('test_paper_answers')->join('test_paper_questions', 'test_paper_questions.id', '=', 'test_paper_answers.question_id')
                    ->where('attempt_id', $paper->attempt->id)->orderBy('position')
                    ->get(array_merge(['position as number', 'answer', 'working'], $handwritingReady ? ['answer_ink', 'working_ink'] : []))
                    ->map(function ($answer) use ($handwritingReady) {
                        foreach (['answer_ink', 'working_ink'] as $field) {
                            $answer->$field = $handwritingReady && $answer->$field ? json_decode($answer->$field, true, flags: JSON_THROW_ON_ERROR) : null;
                        }
                        return $answer;
                    })->all()];
        }
        if ($actor->role !== 'student') $result += ['student_id' => $paper->batch->student_id, 'batch_key' => $paper->batch->batch_key,
            'topics' => $paper->batch->topics, 'blueprint' => $paper->blueprint];
        if ($actor->role !== 'student' && $questions) $result['events'] = $paper->events->map(fn ($e) => $e->only(['actor_id', 'from_status', 'to_status', 'reason', 'created_at']))->all();
        return $result;
    }

    public function questionsInDisplayOrder(TestPaper $paper): Collection
    {
        // The paper ID fixes the shuffle for every viewer. Saved positions still identify responses.
        return $paper->questions->groupBy('topic')->flatMap(function ($topicQuestions) use ($paper) {
            $seeded = $topicQuestions->map(fn ($question) => [
                'question' => $question,
                'key' => hash('sha256', $paper->id.':'.$question->position),
            ])->sortBy('key')->values();

            // Spread each topic across the full paper, including when topic quotas differ.
            return $seeded->map(fn ($item, $index) => $item + [
                'spread' => ($index + 0.5) / $seeded->count(),
            ]);
        })->sortBy([['spread', 'asc'], ['key', 'asc']])->pluck('question')->values();
    }

    public function diagram(TestPaper $paper, string $hash): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $hash), 404);
        $belongs = $paper->questions->contains(fn ($q) => in_array($hash, array_column($q->content['diagrams'] ?? [], 'sha256'), true));
        abort_unless($belongs, 404);
        $encoded = DB::table('test_paper_assets')->where('sha256', $hash)->value('base64_bytes');
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
        abort_unless(is_string($bytes) && hash_equals($hash, hash('sha256', $bytes)), 409, 'Retained diagram unavailable.');
        return $bytes;
    }
}
