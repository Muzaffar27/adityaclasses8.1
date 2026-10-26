<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonQuestionSource;
use App\Services\LessonQuestionBankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LessonQuestionBankController extends Controller
{
    public function show(Request $request, Lesson $lesson, LessonQuestionBankService $service)
    {
        abort_unless(in_array($request->user()->role, ['tutor', 'admin'], true), 403);
        if (!Schema::hasTable('lesson_question_sources')) return response()->json(['available' => false, 'sources' => []]);
        $service->synchronize($lesson);
        return response()->json(['available' => true, 'sources' => LessonQuestionSource::where('lesson_id', $lesson->id)->get()->map(fn ($source) => [
            'slot' => $source->slot, 'status' => $source->status, 'revision' => $source->revision,
            'question_count' => $source->question_count, 'rejected_count' => $source->rejected_count,
            'processed_at' => $source->processed_at?->toIso8601String(), 'error' => $source->last_error,
        ])]);
    }
}
