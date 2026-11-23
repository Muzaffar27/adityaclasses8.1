<?php

namespace App\Http\Controllers;

use App\Models\TestPaper;
use App\Services\SavedTestPaperService;
use Illuminate\Http\Request;

class TestPaperController extends Controller
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Vary' => 'Authorization, Cookie'];

    public function index(Request $request, SavedTestPaperService $service)
    {
        $service->ensureReady();
        abort_unless(in_array($request->user()->role, ['student', 'tutor', 'admin'], true), 403);
        $input = $request->validate(['per_page' => 'sometimes|integer|min:1|max:20']);
        $page = TestPaper::with(['batch', 'attempt'])->whereHas('batch', fn ($q) => $q->where('student_id', $request->user()->id))
            ->orderByRaw("CASE WHEN status IN ('available', 'started') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status IN ('available', 'started') THEN available_at END ASC")
            ->orderByDesc('created_at')->orderBy('id')->paginate($input['per_page'] ?? 20);
        return response()->json(['data' => $page->map(fn ($paper) => $service->payload($paper, $request->user(), false)),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()], 200, self::PRIVATE_HEADERS);
    }

    public function store(Request $request, SavedTestPaperService $service)
    {
        $service->staff($request->user());
        $input = $request->validate(['student_id' => 'required|integer|exists:users,id', 'grade_id' => 'required|integer|exists:grades,id',
            'subject_id' => 'required|integer|exists:subjects,id', 'batch_key' => 'required|uuid',
            'purpose' => 'required|in:main,revision,follow_up', 'topics' => 'required|array|size:3', 'topics.*' => 'required|string|max:255|distinct:strict']);
        $paper = $service->assign($request->user(), $input);
        return response()->json($service->payload($paper, $request->user()), 200, self::PRIVATE_HEADERS);
    }

    public function adminIndex(Request $request, SavedTestPaperService $service)
    {
        $service->staff($request->user()); $service->ensureReady();
        $input = $request->validate(['student_id' => 'sometimes|integer|min:1']);
        $page = TestPaper::with(['batch', 'attempt'])->when(isset($input['student_id']), fn ($q) => $q->whereHas('batch',
            fn ($b) => $b->where('student_id', $input['student_id'])))->orderByDesc('created_at')->orderBy('id')->paginate(20);
        return response()->json(['data' => $page->map(fn ($paper) => $service->payload($paper, $request->user(), false)),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()], 200, self::PRIVATE_HEADERS);
    }

    public function attention(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['student', 'tutor', 'admin'], true), 403);
        $empty = ['total' => 0, 'available_count' => 0, 'started_count' => 0, 'next' => null];
        foreach (['assessment_batches', 'test_papers', 'test_paper_attempts', 'test_paper_questions'] as $table) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return response()->json($empty, 200, self::PRIVATE_HEADERS);
        }
        $student = $request->user()->id;
        $access = \App\Models\LessonAccess::query()->selectRaw('1')->where('user_id', $student)->active()
            ->whereColumn('lesson_access.grade_id', 'assessment_batches.grade_id')
            ->whereColumn('lesson_access.subject_id', 'assessment_batches.subject_id')->toBase();
        $staff = in_array($request->user()->role, ['tutor', 'admin'], true);
        $papers = TestPaper::whereIn('status', ['available', 'started'])
            ->whereHas('batch', fn ($batch) => $batch->where('student_id', $student))
            ->when(!$staff, fn ($papers) => $papers->where(fn ($query) => $query->where('status', 'started')
                ->orWhereHas('batch', fn ($batch) => $batch->whereExists($access))));
        $counts = (clone $papers)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $next = $papers->orderBy('available_at')->orderBy('id')->first(['id', 'status', 'grade_name', 'subject_name']);
        $available = (int) ($counts['available'] ?? 0);
        $started = (int) ($counts['started'] ?? 0);
        return response()->json(['total' => $available + $started, 'available_count' => $available, 'started_count' => $started,
            'next' => $next ? ['id' => $next->id, 'status' => $next->status, 'grade' => $next->grade_name, 'subject' => $next->subject_name] : null],
            200, self::PRIVATE_HEADERS);
    }

    public function show(Request $request, string $paper, SavedTestPaperService $service)
    {
        $record = $service->findFor($request->user(), $paper);
        return response()->json($service->payload($record, $request->user()), 200, self::PRIVATE_HEADERS);
    }

    public function start(Request $request, string $paper, SavedTestPaperService $service)
    {
        $record = $service->start($request->user(), $paper);
        return response()->json($service->payload($record, $request->user()), 200, self::PRIVATE_HEADERS);
    }

    public function saveAnswers(Request $request, string $paper, \App\Services\TestPaperResponseService $responses, SavedTestPaperService $service)
    {
        $input = $request->validate(['revision' => 'required|integer|min:0', 'answers' => 'required|array|min:1|max:15',
            'answers.*.number' => 'required|integer|between:1,15|distinct', 'answers.*.answer' => 'present|nullable|string|max:1000',
            'answers.*.working' => 'present|nullable|string|max:8000', 'answers.*.photo' => 'prohibited', 'answers.*.file' => 'prohibited',
            'answers.*.answer_ink' => 'prohibited',
            'answers.*.working_ink' => ['sometimes', 'nullable', new \App\Rules\Handwriting],
            'answers.*.attachments' => 'prohibited', 'photo' => 'prohibited', 'files' => 'prohibited']);
        $record = $responses->save($request->user(), $paper, $input['revision'], $input['answers']);
        return response()->json(['revision' => (int) \Illuminate\Support\Facades\DB::table('test_paper_response_versions')
            ->where('attempt_id', $record->attempt->id)->value('revision')], 200, self::PRIVATE_HEADERS);
    }

    public function submit(Request $request, string $paper, \App\Services\TestPaperResponseService $responses, SavedTestPaperService $service)
    {
        $input = $request->validate(['revision' => 'required|integer|min:0']);
        $record = $responses->submit($request->user(), $paper, $input['revision']);
        return response()->json($service->payload($record, $request->user()), 200, self::PRIVATE_HEADERS);
    }

    public function cancel(Request $request, string $paper, SavedTestPaperService $service)
    {
        $service->staff($request->user());
        $input = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        $record = $service->cancel($request->user(), $paper, $input['reason']);
        return response()->json($service->payload($record, $request->user()), 200, self::PRIVATE_HEADERS);
    }

    public function replace(Request $request, string $paper, SavedTestPaperService $service)
    {
        $service->staff($request->user());
        $input = $request->validate(['request_key' => 'required|uuid', 'reason' => 'required|string|min:3|max:1000']);
        $record = $service->replace($request->user(), $paper, $input['request_key'], $input['reason']);
        return response()->json($service->payload($record, $request->user()), 200, self::PRIVATE_HEADERS);
    }

    public function diagram(Request $request, string $paper, string $hash, SavedTestPaperService $service)
    {
        $record = $service->findFor($request->user(), $paper);
        return response($service->diagram($record, $hash), 200, self::PRIVATE_HEADERS + ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
    }
}
