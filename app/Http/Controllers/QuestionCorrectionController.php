<?php

namespace App\Http\Controllers;

use App\Models\QuestionCorrection;
use App\Models\QuestionReview;
use App\Services\QuestionCorrectionService;
use Illuminate\Http\Request;

class QuestionCorrectionController extends Controller
{
    private function authorizeStaff(Request $request, QuestionCorrectionService $service): void
    {
        abort_unless(in_array($request->user()->role, ['tutor', 'admin'], true), 403);
        abort_unless($service->available(), 503, 'Question correction setup is pending.');
    }

    public function index(Request $request, QuestionCorrectionService $service)
    {
        $this->authorizeStaff($request, $service);
        $input = $request->validate(['page' => 'nullable|integer|min:1', 'status' => 'nullable|in:open,resolved,all']);
        $query = QuestionCorrection::query();
        if (($input['status'] ?? 'open') === 'open') $query->where('status', '!=', 'resolved');
        elseif ($input['status'] === 'resolved') $query->where('status', 'resolved');
        $rows = $query->orderBy('flagged_at')->paginate(20);
        $reviews = QuestionReview::whereIn('id', $rows->pluck('question_id')->merge($rows->pluck('replacement_id'))->filter()->unique())->get()->keyBy('id');
        return response()->json(['data' => $rows->map(function ($issue) use ($reviews) {
            $replacement = $reviews->get($issue->replacement_id);
            $approval = $issue->status === 'review_needed' && $replacement && in_array($replacement->status, ['pending', 'review_again'], true)
                ? ['id' => $replacement->id, 'revision' => $replacement->revision] : null;
            return [
                'id' => $issue->question_id, 'status' => $issue->status, 'reason' => $issue->reason, 'outcome' => $issue->outcome,
                'attempts' => $issue->attempts, 'flagged_at' => $issue->flagged_at->toIso8601String(),
                'content' => $replacement?->content ?? $issue->corrected_content ?? $issue->original['content'],
                'origins' => $replacement?->origins ?? $issue->original['origins'], 'approval' => $approval,
                'replacement_id' => $issue->replacement_id, 'revision' => $reviews->get($issue->question_id)?->revision,
            ];
        }), 'total' => $rows->total(), 'last_page' => $rows->lastPage(), 'page' => $rows->currentPage()]);
    }

    public function flag(Request $request, string $id, QuestionCorrectionService $service)
    {
        $this->authorizeStaff($request, $service);
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id), 404);
        $input = $request->validate(['revision' => 'required|integer|min:1', 'reason' => 'nullable|string|max:1000', 'source_id' => 'nullable|integer|min:1']);
        if (isset($input['source_id'])) {
            $review = QuestionReview::findOrFail($id);
            abort_unless(in_array($input['source_id'], array_column($review->origins, 'source_id'), true), 409, 'The question is no longer attached to this PDF.');
        }
        $review = $service->flag($id, $input['revision'], $request->user()->id, $input['reason'] ?? null);
        return response()->json(['id' => $id, 'status' => $review->status, 'revision' => $review->revision,
            'pdf' => isset($input['source_id']) ? app(\App\Services\QuestionReviewService::class)->pdfReview($input['source_id']) : null]);
    }

    public function correct(Request $request, string $id, QuestionCorrectionService $service)
    {
        $this->authorizeStaff($request, $service);
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id), 404);
        $input = $request->validate(['revision' => 'required|integer|min:1', 'content' => 'required|array', 'approve' => 'sometimes|boolean']);
        $review = $service->correct($id, $input['revision'], $input['content'], $request->user()->id, $input['approve'] ?? false);
        return response()->json(['id' => $review->id, 'status' => $review->status]);
    }
}
