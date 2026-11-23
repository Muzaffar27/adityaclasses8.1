<?php

namespace App\Http\Controllers;

use App\Models\{AutomaticAssessmentBatch, StudentAssessmentEnrolment, User};
use App\Services\{AutomaticAssessmentService, SavedTestPaperService};
use Illuminate\Http\Request;

class AutomaticAssessmentController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Vary' => 'Authorization, Cookie'];

    public function enrol(Request $request, User $student, AutomaticAssessmentService $service, SavedTestPaperService $papers)
    {
        $papers->staff($request->user());
        $input = $request->validate(['grade_id' => 'required|integer|exists:grades,id', 'subject_id' => 'required|integer|exists:subjects,id']);
        return response()->json($service->enrol($request->user(), $student, $input['grade_id'], $input['subject_id']), 200, self::HEADERS);
    }

    public function index(Request $request, AutomaticAssessmentService $service, SavedTestPaperService $papers)
    {
        $papers->staff($request->user()); $service->ensureReady();
        $input = $request->validate(['student_id' => 'sometimes|integer|min:1']);
        $rows = StudentAssessmentEnrolment::when(isset($input['student_id']), fn ($q) => $q->where('student_id', $input['student_id']))
            ->orderByDesc('id')->paginate(20);
        $batches = AutomaticAssessmentBatch::whereIn('enrolment_id', $rows->pluck('id'))->orderByDesc('sequence')->get()->groupBy('enrolment_id');
        return response()->json(['data' => $rows->map(fn ($row) => ['enrolment' => $row, 'batches' => $batches->get($row->id, collect())->take(10)->values()]),
            'page' => $rows->currentPage(), 'last_page' => $rows->lastPage()], 200, self::HEADERS);
    }

    public function reconcile(Request $request, string $enrolment, AutomaticAssessmentService $service, SavedTestPaperService $papers)
    {
        $papers->staff($request->user()); $service->ensureReady();
        return response()->json($service->reconcile((int) $enrolment), 200, self::HEADERS);
    }

    public function update(Request $request, string $enrolment, AutomaticAssessmentService $service, SavedTestPaperService $papers)
    {
        $papers->staff($request->user()); $service->ensureReady();
        $input = $request->validate(['enabled' => 'required|boolean']);
        $row = StudentAssessmentEnrolment::findOrFail($enrolment);
        $row->update(['enabled' => $input['enabled'], 'needs_reconcile' => (bool) $input['enabled'],
            'enabled_by' => $input['enabled'] ? $request->user()->id : $row->enabled_by]);
        return response()->json($row, 200, self::HEADERS);
    }

    public function cancel(Request $request, string $batch, AutomaticAssessmentService $service, SavedTestPaperService $papers)
    {
        $papers->staff($request->user()); $service->ensureReady();
        $input = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        $row = \Illuminate\Support\Facades\DB::transaction(function () use ($batch, $input, $request) {
            $row = AutomaticAssessmentBatch::whereKey($batch)->lockForUpdate()->firstOrFail();
            abort_if($row->paper_id !== null, 409, 'Cancel an issued paper through its paper cancellation action.');
            if ($row->status !== 'cancelled') $row->update(['status' => 'cancelled',
                'details' => ['reason' => $input['reason'], 'actor_id' => $request->user()->id]]);
            return $row;
        });
        return response()->json($row, 200, self::HEADERS);
    }
}
