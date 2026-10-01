<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use App\Services\LessonProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'progress' => app(LessonProgressService::class)->forUser($lesson, $request->user()->id),
        ]);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureReadyAndAccessible($request, $lesson);

        $validated = $request->validate([
            'video_type' => ['required', 'in:lesson,answer'],
            'viewed_only' => ['sometimes', 'boolean'],
            'position_seconds' => ['required_unless:viewed_only,true', 'numeric', 'min:0', 'max:86400'],
            'duration_seconds' => ['nullable', 'numeric', 'min:1', 'max:86400'],
        ]);

        $videoField = $validated['video_type'] === 'answer' ? 'answer_vimeo_url' : 'vimeo_url';
        abort_unless($lesson->{$videoField}, 422, 'This video is not available.');

        if ($validated['viewed_only'] ?? false) {
            $progress = LessonProgress::updateOrCreate([
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'video_type' => $validated['video_type'],
            ], ['last_viewed_at' => now()]);

            return response()->json([
                'saved' => true,
                'completed' => (bool) $progress->completed_at,
                'progress' => app(LessonProgressService::class)->forUser($lesson, $request->user()->id),
            ]);
        }

        $progress = DB::transaction(function () use ($request, $lesson, $validated) {
            $record = LessonProgress::firstOrCreate([
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'video_type' => $validated['video_type'],
            ]);
            $record = LessonProgress::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $position = (float) $validated['position_seconds'];
            $duration = $validated['duration_seconds'] ?? $record->duration_seconds;
            if ($duration !== null && $position > (float) $duration) {
                throw ValidationException::withMessages([
                    'position_seconds' => ['Playback position cannot exceed the video duration.'],
                ]);
            }
            $completed = $duration > 0 && $position / $duration >= LessonProgressService::COMPLETION_THRESHOLD;
            $record->position_seconds = (int) round($position);
            $record->duration_seconds = $duration === null ? null : (int) round($duration);
            // A replay or delayed earlier save must never erase earned completion.
            if ($completed && !$record->completed_at) {
                $record->completed_at = now();
            }
            $record->last_viewed_at = now();
            $record->save();

            return $record;
        });

        return response()->json([
            'saved' => true,
            'completed' => (bool) $progress->completed_at,
            'progress' => app(LessonProgressService::class)->forUser($lesson, $request->user()->id),
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
