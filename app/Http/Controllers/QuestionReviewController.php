<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\QuestionReview;
use App\Services\QuestionReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class QuestionReviewController extends Controller
{
    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['tutor', 'admin'], true), 403);
        abort_unless(Schema::hasTable('question_reviews'), 503, 'Question review setup is pending.');
    }

    public function index(Request $request, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        $filters = $request->validate(['grade_id' => 'nullable|integer', 'subject_id' => 'nullable|integer', 'lesson_id' => 'nullable|integer',
            'status' => 'nullable|in:pending,approved,reprocessing,all', 'page' => 'nullable|integer|min:1']);
        $current = $service->synchronize();
        $rows = QuestionReview::orderBy('created_at')->orderBy('id')->get()->filter(function ($row) use ($current, $filters) {
            if (!isset($current[$row->id]) && !in_array($row->status, ['reprocessing', 'needs_attention'], true)) return false;
            if (!empty($filters['grade_id']) && (int) $row->grade_id !== (int) $filters['grade_id']) return false;
            if (!empty($filters['subject_id']) && (int) $row->subject_id !== (int) $filters['subject_id']) return false;
            if (!empty($filters['lesson_id']) && !in_array((int) $filters['lesson_id'], array_column($row->origins, 'lesson_id'), true)) return false;
            return true;
        });
        $counts = ['pending' => $rows->whereIn('status', ['pending', 'review_again', 'needs_attention'])->count(),
            'approved' => $rows->where('status', 'approved')->count(), 'reprocessing' => $rows->where('status', 'reprocessing')->count()];
        $status = $filters['status'] ?? 'pending';
        if ($status !== 'all') $rows = $rows->whereIn('status', $status === 'pending' ? ['pending', 'review_again', 'needs_attention'] : [$status]);
        $page = (int) ($filters['page'] ?? 1);
        $items = $rows->values()->slice(($page - 1) * 20, 20)->map(fn ($row) => [
            'id' => $row->id, 'grade_id' => $row->grade_id, 'subject_id' => $row->subject_id,
            'content' => $row->content, 'origins' => $row->origins, 'status' => $row->status,
            'revision' => $row->revision, 'review_cycles' => $row->review_cycles,
            'retry_outcome' => $row->retry_outcome, 'review_note' => $row->review_note,
            'current' => isset($current[$row->id]), 'approved_at' => $row->approved_at?->toIso8601String(),
        ])->values();
        $lessons = collect($current)->flatMap(fn ($q) => array_map(fn ($origin) => ['id' => $origin['lesson_id'], 'title' => $origin['lesson_title'],
            'grade_id' => $q['grade_id'], 'subject_id' => $q['subject_id'], 'topic' => $origin['topic']], $q['origins']))->unique('id')->values();
        return response()->json(['data' => $items, 'counts' => $counts, 'total' => $rows->count(), 'page' => $page,
            'last_page' => max(1, (int) ceil($rows->count() / 20)), 'grades' => Grade::select('id', 'name')->get(),
            'subjects' => Subject::select('id', 'name')->get(), 'lessons' => $lessons]);
    }

    public function decide(Request $request, string $id, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id), 404);
        $input = $request->validate(['approved' => 'required|boolean', 'revision' => 'required|integer|min:1', 'note' => 'nullable|string|max:1000']);
        $review = $service->decide($id, $input['approved'], $input['revision'], $request->user()->id, $input['note'] ?? null);
        return response()->json(['id' => $review->id, 'status' => $review->status, 'revision' => $review->revision]);
    }
}
