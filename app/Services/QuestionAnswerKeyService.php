<?php

namespace App\Services;

use App\Models\QuestionAnswerKey;
use App\Models\QuestionReview;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestionAnswerKeyService
{
    public function __construct(private QuestionReviewService $reviews, private ExactMathAnswerService $maths) {}

    public function authorize(User $actor): void
    {
        abort_unless(in_array($actor->role, ['tutor', 'admin'], true), 403);
        abort_unless(Schema::hasTable('question_answer_keys'), 503, 'Answer key setup is pending.');
    }

    public function contentHash(array $content): string
    {
        $canonical = function ($value) use (&$canonical) {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value);
            return array_map($canonical, $value);
        };
        return hash('sha256', json_encode($canonical($content), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function currentQuestion(string $id): array
    {
        $review = QuestionReview::findOrFail($id);
        abort_unless($review->status === 'approved', 409, 'The question must be approved before preparing its answer key.');
        $questions = $this->reviews->currentQuestions();
        abort_unless(isset($questions[$id]), 409, 'The question source changed or is unavailable.');
        $q = $questions[$id];
        abort_unless((int) $q['grade_id'] === (int) $review->grade_id && (int) $q['subject_id'] === (int) $review->subject_id, 409, 'Question classification changed.');
        return $q;
    }

    public function generate(User $actor, string $id): array
    {
        $this->authorize($actor);
        return DB::transaction(function () use ($actor, $id) {
            QuestionReview::whereKey($id)->lockForUpdate()->firstOrFail();
            $q = $this->currentQuestion($id);
            $hash = $this->contentHash($q['content']);
            $current = $this->approvedFor([$id => $q]);
            if (isset($current[$id])) return ['reused' => true, 'key' => $current[$id]];
            $request = hash('sha256', ExactMathAnswerService::VERSION.':'.$hash);
            $existing = QuestionAnswerKey::where('question_id', $id)->where('request_key', $request)->first();
            if ($existing) return ['reused' => true, 'key' => $existing];
            $suggestion = $this->maths->suggest($q['content']);
            if (!$suggestion['supported']) return ['needs_tutor' => true, 'reason' => $suggestion['reason']];
            return ['reused' => false, 'key' => $this->create($actor, $q, $suggestion['answer'], $request, ExactMathAnswerService::VERSION)];
        }, 3);
    }

    public function draft(User $actor, string $id, string $request, string $expectedHash, array $answer): QuestionAnswerKey
    {
        $this->authorize($actor);
        return DB::transaction(function () use ($actor, $id, $request, $expectedHash, $answer) {
            QuestionReview::whereKey($id)->lockForUpdate()->firstOrFail();
            $q = $this->currentQuestion($id);
            abort_unless(hash_equals($this->contentHash($q['content']), $expectedHash), 409, 'The question changed. Reload it before saving.');
            return $this->create($actor, $q, $answer, $request, 'tutor');
        }, 3);
    }

    private function create(User $actor, array $q, array $answer, string $request, string $generator): QuestionAnswerKey
    {
        $answer = $this->validateAnswer($answer);
        $validation = $this->validateMaths($q['content'], $answer);
        $hash = $this->contentHash($q['content']);
        $requestHash = $this->contentHash(['question' => $hash, 'answer' => $answer, 'generator' => $generator]);
        $existing = QuestionAnswerKey::where('question_id', $q['id'])->where('request_key', $request)->first();
        if ($existing) {
            abort_unless(hash_equals($existing->request_hash, $requestHash), 409, 'This request key belongs to another answer draft.');
            return $existing;
        }
        return QuestionAnswerKey::create(['id' => (string) Str::uuid(), 'question_id' => $q['id'],
            'version' => (int) QuestionAnswerKey::where('question_id', $q['id'])->max('version') + 1,
            'request_key' => $request, 'request_hash' => $requestHash, 'question_content_hash' => $hash,
            'question_snapshot' => $q['content'], 'answer' => $answer, 'validation' => $validation,
            'generator' => $generator, 'status' => 'draft', 'created_by' => $actor->id]);
    }

    public function approve(User $actor, string $id, string $expectedHash): QuestionAnswerKey
    {
        $this->authorize($actor);
        $initial = QuestionAnswerKey::findOrFail($id);
        return DB::transaction(function () use ($actor, $initial, $expectedHash) {
            QuestionReview::whereKey($initial->question_id)->lockForUpdate()->firstOrFail();
            $key = QuestionAnswerKey::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            $q = $this->currentQuestion($key->question_id);
            abort_unless(hash_equals($key->question_content_hash, $expectedHash)
                && hash_equals($expectedHash, $this->contentHash($q['content'])), 409, 'This question version changed.');
            abort_unless(in_array($key->status, ['draft', 'approved'], true), 409, 'A revoked answer key cannot be approved again.');
            if ($key->status === 'approved') return $key;
            $newer = QuestionAnswerKey::where('question_id', $key->question_id)->where('version', '>', $key->version)->whereNotNull('approved_at')->exists();
            abort_if($newer, 409, 'A newer reviewed answer version exists. Create a new draft instead.');
            $this->validateMaths($q['content'], $key->answer);
            abort_unless($key->answer['marks'] !== null, 422, 'Set the marks in a new draft before approving this answer key.');
            abort_unless($key->answer['marking_method'] !== 'tutor_review' || $key->answer['rubric'], 422, 'A tutor-reviewed answer needs a marking rubric before approval.');
            $key->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            return $key;
        }, 3);
    }

    public function revoke(User $actor, string $id, string $reason): QuestionAnswerKey
    {
        $this->authorize($actor);
        $initial = QuestionAnswerKey::findOrFail($id);
        return DB::transaction(function () use ($actor, $initial, $reason) {
            QuestionReview::whereKey($initial->question_id)->lockForUpdate()->firstOrFail();
            $key = QuestionAnswerKey::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            if ($key->status === 'revoked') return $key;
            $key->update(['status' => 'revoked', 'revoked_by' => $actor->id, 'revoked_at' => now(), 'revocation_reason' => $reason]);
            return $key;
        }, 3);
    }

    /** Current bank must already be source-validated by the caller. No student data here. */
    public function approvedFor(array $questions): array
    {
        if (!$questions || !Schema::hasTable('question_answer_keys')) return [];
        $rows = QuestionAnswerKey::whereIn('question_id', array_keys($questions))->whereIn('status', ['approved', 'revoked'])->whereNotNull('approved_at')
            ->orderByDesc('version')->get()->unique('question_id');
        $result = [];
        foreach ($rows as $key) if ($key->status === 'approved' && hash_equals($key->question_content_hash, $this->contentHash($questions[$key->question_id]['content']))) {
            $result[$key->question_id] = $key;
        }
        return $result;
    }

    public function validateAnswer(array $answer): array
    {
        $data = Validator::make($answer, ['final_answer' => 'required|string|max:2000', 'accepted_answers' => 'present|array|max:20',
            'accepted_answers.*' => 'required|string|max:200', 'working' => 'required|array|min:1|max:40', 'working.*' => 'required|string|max:2000',
            'marks' => ['present', 'nullable', 'numeric', 'min:0.01', 'max:1000', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'marking_method' => 'required|in:exact_numeric,tutor_review',
            'answer_form' => 'required|in:equivalent_numeric,lowest_terms_fraction,decimal,tutor_review', 'unit' => 'present|nullable|string|max:30',
            'rubric' => 'present|array|max:20', 'rubric.*.description' => 'required|string|max:500',
            'rubric.*.marks' => ['required', 'numeric', 'min:0.01', 'max:1000', 'regex:/^\d+(?:\.\d{1,2})?$/D']])->validate();
        foreach (['accepted_answers', 'working', 'rubric'] as $field) {
            if (!array_is_list($data[$field])) throw ValidationException::withMessages([$field => ['Use an ordered list.']]);
        }
        if ($data['marks'] !== null) $data['marks'] = number_format((float) $data['marks'], 2, '.', '');
        $data['rubric'] = array_map(fn ($item) => ['description' => $item['description'], 'marks' => number_format((float) $item['marks'], 2, '.', '')], $data['rubric']);
        if ($data['rubric'] && ($data['marks'] === null || array_sum(array_map(fn ($r) => (int) round((float) $r['marks'] * 100), $data['rubric'])) !== (int) round((float) $data['marks'] * 100))) {
            throw ValidationException::withMessages(['rubric' => ['Rubric marks must add up to total marks.']]);
        }
        return $data;
    }

    private function validateMaths(array $content, array $answer): array
    {
        $suggestion = $this->maths->suggest($content);
        try {
            $final = $answer['marking_method'] === 'exact_numeric' ? $this->maths->literal($answer['final_answer']) : null;
            if ($final && !$this->maths->acceptsForm($answer['final_answer'], $answer['answer_form'])) throw new DomainException('Final answer does not meet its required answer form.');
            if (!$final && $answer['answer_form'] !== 'tutor_review') throw new DomainException('Tutor-reviewed answers need the tutor_review answer form and rubric guidance.');
            if ($suggestion['supported'] && $answer['marking_method'] === 'exact_numeric'
                && $final !== [$suggestion['exact_value']['numerator'], $suggestion['exact_value']['denominator']]) {
                throw new DomainException('The proposed answer disagrees with exact arithmetic.');
            }
            if ($final) foreach ($answer['accepted_answers'] as $accepted) {
                if ($this->maths->literal($accepted) !== $final || !$this->maths->acceptsForm($accepted, $answer['answer_form'])) {
                    throw new DomainException('Accepted numerical answers must have the same value and meet the required answer form.');
                }
            }
        } catch (DomainException $e) { throw ValidationException::withMessages(['answer' => [$e->getMessage()]]); }
        return ['method' => $suggestion['supported'] && $answer['marking_method'] === 'exact_numeric' ? 'exact_arithmetic_agrees' : 'tutor_verification_required',
            'solver_version' => ExactMathAnswerService::VERSION, 'human_approval_required' => true];
    }
}
