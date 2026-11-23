<?php

namespace App\Http\Controllers;

use App\Models\QuestionAnswerKey;
use App\Services\QuestionAnswerKeyService;
use App\Services\TestPaperReadinessService;
use Illuminate\Http\Request;

class QuestionAnswerKeyController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Vary' => 'Authorization, Cookie'];

    public function index(Request $request, QuestionAnswerKeyService $service, TestPaperReadinessService $readiness)
    {
        $service->authorize($request->user());
        $input = $request->validate(['page' => 'sometimes|integer|min:1', 'grade_id' => 'sometimes|integer|min:1',
            'subject_id' => 'sometimes|integer|min:1', 'topic' => 'sometimes|string|max:255']);
        $questions = array_filter($readiness->snapshot()['approved'], fn ($q) => (!isset($input['grade_id']) || (int) $q['grade_id'] === (int) $input['grade_id'])
            && (!isset($input['subject_id']) || (int) $q['subject_id'] === (int) $input['subject_id'])
            && (!isset($input['topic']) || in_array($input['topic'], array_column($q['origins'], 'topic'), true)));
        usort($questions, fn ($a, $b) => strcmp($a['id'], $b['id']));
        $page = $input['page'] ?? 1; $total = count($questions);
        $questions = array_column(array_slice($questions, ($page - 1) * 20, 20), null, 'id');
        $approved = $service->approvedFor($questions);
        $latest = QuestionAnswerKey::whereIn('question_id', array_keys($questions))->orderByDesc('version')->get()->unique('question_id')->keyBy('question_id');
        $data = array_map(fn ($q) => ['question' => $q, 'question_content_hash' => $service->contentHash($q['content']),
            'approved_key' => $approved[$q['id']] ?? null, 'latest_version' => $latest->get($q['id'])], array_values($questions));
        return response()->json(['data' => $data, 'total' => $total, 'page' => $page, 'last_page' => max(1, (int) ceil($total / 20))], 200, self::HEADERS);
    }

    public function show(Request $request, string $question, QuestionAnswerKeyService $service)
    {
        $service->authorize($request->user());
        $q = $service->currentQuestion($question);
        $keys = QuestionAnswerKey::where('question_id', $question)->orderByDesc('version')->paginate(20);
        return response()->json(['question' => $q, 'question_content_hash' => $service->contentHash($q['content']),
            'versions' => $keys->items(), 'page' => $keys->currentPage(), 'last_page' => $keys->lastPage()], 200, self::HEADERS);
    }

    public function generate(Request $request, string $question, QuestionAnswerKeyService $service)
    {
        return response()->json($service->generate($request->user(), $question), 200, self::HEADERS);
    }

    public function store(Request $request, string $question, QuestionAnswerKeyService $service)
    {
        $service->authorize($request->user());
        $input = $request->validate(['request_key' => 'required|uuid', 'question_content_hash' => 'required|regex:/^[a-f0-9]{64}$/D', 'answer' => 'required|array']);
        return response()->json($service->draft($request->user(), $question, $input['request_key'], $input['question_content_hash'], $input['answer']), 200, self::HEADERS);
    }

    public function approve(Request $request, string $key, QuestionAnswerKeyService $service)
    {
        $service->authorize($request->user());
        $input = $request->validate(['question_content_hash' => 'required|regex:/^[a-f0-9]{64}$/D']);
        return response()->json($service->approve($request->user(), $key, $input['question_content_hash']), 200, self::HEADERS);
    }

    public function revoke(Request $request, string $key, QuestionAnswerKeyService $service)
    {
        $service->authorize($request->user());
        $input = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        return response()->json($service->revoke($request->user(), $key, $input['reason']), 200, self::HEADERS);
    }
}
