<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LessonProgressController extends Controller
{
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureReadyAndAccessible($request, $lesson);
        $videoType = $request->validate([
            'video_type' => ['nullable', 'in:lesson,answer'],
        ])['video_type'] ?? 'lesson';

        $progress = LessonProgress::where('user_id', $request->user()->id)
            ->where('lesson_id', $lesson->id)
            ->where('video_type', $videoType)
            ->first();

        return response()->json([
            'position_seconds' => $progress?->position_seconds ?? 0,
            'duration_seconds' => $progress?->duration_seconds,
            'completed' => (bool) $progress?->completed_at,
        ]);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureReadyAndAccessible($request, $lesson);

        $validated = $request->validate([
            'video_type' => ['required', 'in:lesson,answer'],
            'viewed_only' => ['sometimes', 'boolean'],
            'position_seconds' => ['required_unless:viewed_only,true', 'numeric', 'min:0', 'max:86400'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);

        if ($validated['viewed_only'] ?? false) {
            $progress = LessonProgress::firstOrNew([
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'video_type' => $validated['video_type'],
            ]);
            $progress->last_viewed_at = now();
            $progress->save();

            return response()->json([
                'saved' => true,
                'completed' => (bool) $progress->completed_at,
            ]);
        }

        $position = (int) round($validated['position_seconds']);
        $duration = isset($validated['duration_seconds'])
            ? (int) round($validated['duration_seconds'])
            : null;
        $completed = $duration && $duration > 0 && ($position / $duration) >= 0.95;

        $progress = LessonProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'video_type' => $validated['video_type'],
            ],
            [
                'position_seconds' => $completed ? 0 : $position,
                'duration_seconds' => $duration,
                'completed_at' => $completed ? now() : null,
                'last_viewed_at' => now(),
            ]
        );

        return response()->json([
            'saved' => true,
            'completed' => (bool) $progress->completed_at,
        ]);
    }

    private function ensureReadyAndAccessible(Request $request, Lesson $lesson): void
    {
        abort_unless(Schema::hasTable('lesson_progress'), 503, 'Lesson progress is not ready yet.');
        abort_unless(in_array($request->user()?->role, ['student', 'tutor', 'admin'], true), 403);
        abort_unless($lesson->is_active, 404);

        $hasAccess = LessonAccess::where('user_id', $request->user()->id)
            ->where('subject_id', $lesson->subject_id)
            ->where('grade_id', $lesson->grade_id)
            ->where('status', 'accepted')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        abort_unless($hasAccess, 403);
    }
}
