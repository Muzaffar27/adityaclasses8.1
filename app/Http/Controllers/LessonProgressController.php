<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use App\Services\LessonProgressService;
use App\Services\StudentLearningActivityService;
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

        $progress = $this->currentProgress($lesson, $request->user()->id, $videoType);

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
            'activity_session_id' => ['nullable', 'uuid'],
            'activity_active' => ['sometimes', 'boolean'],
        ]);

        $videoField = $validated['video_type'] === 'answer' ? 'answer_vimeo_url' : 'vimeo_url';
        abort_unless($lesson->{$videoField}, 422, 'This video is not available.');

        $progress = DB::transaction(function () use ($request, $lesson, $validated, $videoField) {
            $service = app(LessonProgressService::class);
            $source = $service->videoSource($lesson->{$videoField});
            $record = LessonProgress::firstOrCreate([
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'video_type' => $validated['video_type'],
            ]);
            $record = LessonProgress::whereKey($record->id)->lockForUpdate()->firstOrFail();

            $sourceChanged = !hash_equals((string) $source, (string) $record->video_source);
            if ($sourceChanged) {
                $record->video_source = $source;
                $record->position_seconds = 0;
                $record->duration_seconds = null;
                $record->video_started_at = now();
                $record->completed_at = null;
                if ($request->user()->role === 'student') {
                    app(StudentLearningActivityService::class)->recordActivity($record, [], true);
                }
            }

            if ($validated['viewed_only'] ?? false) {
                $record->last_viewed_at = now();
                $record->save();

                return $record;
            }

            $position = (float) $validated['position_seconds'];
            $duration = $validated['duration_seconds'] ?? $record->duration_seconds;
            if ($duration !== null && $position > (float) $duration) {
                throw ValidationException::withMessages([
                    'position_seconds' => ['Playback position cannot exceed the video duration.'],
                ]);
            }
            if ($duration !== null && $record->duration_seconds !== null
                && abs($duration - $record->duration_seconds) > LessonProgressService::DURATION_TOLERANCE_SECONDS) {
                throw ValidationException::withMessages([
                    'duration_seconds' => ['Video duration does not match the started video.'],
                ]);
            }

            $record->video_started_at ??= now();
            $completed = $duration > 0 && $position / $duration >= LessonProgressService::COMPLETION_THRESHOLD;
            if ($completed && $record->video_started_at->greaterThan(now()->subSeconds($service->completionDelay((int) $duration)))) {
                throw ValidationException::withMessages([
                    'position_seconds' => ['Watch more of the video before marking it complete.'],
                ]);
            }
            $record->position_seconds = (int) round($position);
            $record->duration_seconds = $duration === null ? null : (int) round($duration);
            // A replay or delayed earlier save must never erase earned completion.
            if ($completed && !$record->completed_at) {
                $record->completed_at = now();
            }
            $record->last_viewed_at = now();
            if ($request->user()->role === 'student') {
                app(StudentLearningActivityService::class)->recordActivity($record, $validated, $sourceChanged);
            }
            $record->save();

            return $record;
        });

        return response()->json([
            'saved' => true,
            'completed' => (bool) $progress->completed_at,
            'progress' => app(LessonProgressService::class)->forUser($lesson, $request->user()->id),
        ]);
    }

    private function currentProgress(Lesson $lesson, int $userId, string $videoType): ?LessonProgress
    {
        $field = $videoType === 'answer' ? 'answer_vimeo_url' : 'vimeo_url';
        $source = app(LessonProgressService::class)->videoSource($lesson->{$field});

        return LessonProgress::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->where('video_type', $videoType)
            ->where('video_source', $source)
            ->first();
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
