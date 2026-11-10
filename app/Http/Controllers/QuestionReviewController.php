<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\QuestionReview;
use App\Services\QuestionReviewService;
use App\Services\StarterQuestionReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
            'status' => 'nullable|in:pending,approved,reprocessing,all,rejected,skipped', 'page' => 'nullable|integer|min:1', 'review_scope' => 'nullable|in:all,starter', 'topic' => 'nullable|string|max:255']);
        $current = $service->synchronize();
        $matches = function ($grade, $subject, $origins) use ($filters) {
            if (!empty($filters['grade_id']) && (int) $grade !== (int) $filters['grade_id']) return false;
            if (!empty($filters['subject_id']) && (int) $subject !== (int) $filters['subject_id']) return false;
            if (!empty($filters['lesson_id']) && !in_array((int) $filters['lesson_id'], array_column($origins, 'lesson_id'), true)) return false;
            if (!empty($filters['topic']) && !in_array($filters['topic'], array_column($origins, 'topic'), true)) return false;
            return true;
        };
        $allIssues = collect($service->currentExtractionIssues());
        $issues = $allIssues->filter(fn ($row) => $matches($row['grade_id'], $row['subject_id'], $row['origins']));
        $reviews = QuestionReview::orderBy('created_at')->orderBy('id')->get();
        // Global sidebar totals are independent of review filters and starter-bank scope.
        $corrections = Schema::hasTable('question_corrections')
            ? \App\Models\QuestionCorrection::where('status', '!=', 'resolved')->get(['question_id', 'replacement_id']) : collect();
        $flaggedIds = $corrections->pluck('question_id')->merge($corrections->pluck('replacement_id'))->filter()->unique();
        $notificationCounts = [
            'important' => $corrections->count() + $reviews->filter(fn ($row) => $row->status === 'needs_attention'
                || ($row->status === 'needs_correction' && isset($current[$row->id])))->whereNotIn('id', $flaggedIds)->count(),
            'optional' => $reviews->filter(fn ($row) => isset($current[$row->id]))->whereIn('status', ['pending', 'review_again'])->whereNotIn('id', $flaggedIds)->count(),
        ];
        $selection = app(StarterQuestionReviewService::class)->select($current, $reviews);
        $rows = $reviews->filter(function ($row) use ($current, $matches) {
            if (!isset($current[$row->id]) && !in_array($row->status, ['reprocessing', 'needs_attention'], true)) return false;
            return $matches($row->grade_id, $row->subject_id, $row->origins);
        });
        $counts = ['pending' => $rows->whereIn('status', ['pending', 'review_again', 'needs_attention'])->count(),
            'approved' => $rows->where('status', 'approved')->count(), 'reprocessing' => $rows->where('status', 'reprocessing')->count(),
            'rejected' => $issues->where('status', 'rejected')->count(), 'skipped' => $issues->where('status', 'skipped')->count(),
            'corrections' => Schema::hasTable('question_corrections') ? \App\Models\QuestionCorrection::where('status', '!=', 'resolved')->count() : 0];
        $status = $filters['status'] ?? 'pending';
        $counts['library_pending'] = $counts['pending'];
        if (($filters['review_scope'] ?? 'all') === 'starter') {
            $counts['pending'] = $rows->whereIn('id', $selection['ids'])->count();
            if ($status === 'pending') $rows = $rows->whereIn('id', $selection['ids']);
        }
        $coverage = in_array($status, ['rejected', 'skipped'], true);
        if ($coverage) $rows = $issues->where('status', $status);
        elseif ($status !== 'all') $rows = $rows->whereIn('status', $status === 'pending' ? ['pending', 'review_again', 'needs_attention'] : [$status]);
        $page = (int) ($filters['page'] ?? 1);
        $slice = $rows->values()->slice(($page - 1) * 20, 20);
        $items = $coverage ? $slice->values() : $slice->map(fn ($row) => [
            'id' => $row->id, 'grade_id' => $row->grade_id, 'subject_id' => $row->subject_id,
            'content' => $row->content, 'origins' => $row->origins, 'status' => $row->status,
            'revision' => $row->revision, 'review_cycles' => $row->review_cycles,
            'retry_outcome' => $row->retry_outcome, 'review_note' => $row->review_note,
            'current' => isset($current[$row->id]), 'approved_at' => $row->approved_at?->toIso8601String(),
        ])->values();
        $lessons = collect($current)->concat($allIssues)->flatMap(fn ($q) => array_map(fn ($origin) => ['id' => $origin['lesson_id'], 'title' => $origin['lesson_title'],
            'grade_id' => $q['grade_id'], 'subject_id' => $q['subject_id'], 'topic' => $origin['topic']], $q['origins']))->unique('id')->values();
        return response()->json(['data' => $items, 'counts' => $counts, 'notification_counts' => $notificationCounts, 'total' => $rows->count(), 'page' => $page,
            'last_page' => max(1, (int) ceil($rows->count() / 20)), 'grades' => Grade::select('id', 'name')->get(),
            'subjects' => Subject::select('id', 'name')->get(), 'lessons' => $lessons, 'selection' => $selection]);
    }

    public function pdfs(Request $request, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        $filters = $request->validate(['grade_id' => 'nullable|integer', 'subject_id' => 'nullable|integer', 'lesson_id' => 'nullable|integer', 'review_scope' => 'nullable|in:all,starter', 'topic' => 'nullable|string|max:255']);
        $current = $service->synchronize();
        $reviews = QuestionReview::whereIn('id', array_keys($current))->get();
        $selection = app(StarterQuestionReviewService::class)->select($current, $reviews);
        $pending = ($filters['review_scope'] ?? 'all') === 'starter' ? $selection['ids'] : $reviews->whereIn('status', ['pending', 'review_again'])->pluck('id')->all();
        $sources = [];
        foreach ($current as $question) {
            if (!in_array($question['id'], $pending, true)) continue;
            if (!empty($filters['grade_id']) && (int) $filters['grade_id'] !== (int) $question['grade_id']) continue;
            if (!empty($filters['subject_id']) && (int) $filters['subject_id'] !== (int) $question['subject_id']) continue;
            foreach ($question['origins'] as $origin) {
                if (!empty($filters['lesson_id']) && (int) $filters['lesson_id'] !== (int) $origin['lesson_id']) continue;
                if (!empty($filters['topic']) && $filters['topic'] !== $origin['topic']) continue;
                $id = $origin['source_id'];
                $sources[$id] ??= $origin + ['question_ids' => []];
                $sources[$id]['question_ids'][$question['id']] = true;
            }
        }
        return response()->json(['data' => array_values(array_map(function ($source) {
            $source['pending_count'] = count($source['question_ids']);
            unset($source['question_ids']);
            return $source;
        }, $sources)), 'selection' => $selection]);
    }

    public function pdf(Request $request, int $source, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        return response()->json($service->pdfReview($source));
    }

    public function approvePdf(Request $request, int $source, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        $input = $request->validate(['snapshot' => 'required|string|size:64', 'flagged' => 'present|array',
            'flagged.*' => 'required|string|size:64|distinct', 'question_ids' => 'sometimes|array|max:5000', 'question_ids.*' => 'required|string|size:64|distinct']);
        return response()->json(['approved_count' => $service->approvePdf($source, $input['snapshot'], $input['flagged'], $request->user()->id, $input['question_ids'] ?? null)]);
    }

    public function diagram(Request $request, string $id, string $index, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id) && preg_match('/^\d{1,2}$/', $index), 404);
        $question = $service->currentQuestions()[$id] ?? null;
        $diagram = $question['content']['diagrams'][(int) $index] ?? null;
        abort_unless($diagram, 404);
        return response()->file(Storage::disk('local')->path($diagram['path']), [
            'Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function retryIssue(Request $request, string $id, QuestionReviewService $service)
    {
        $this->authorizeStaff($request);
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id), 404);
        $input = $request->validate(['revision' => 'required|integer|min:1']);
        return response()->json($service->retryExtractionIssue($id, $input['revision']), 202);
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
