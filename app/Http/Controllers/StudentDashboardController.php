<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StudentDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(in_array($user?->role, ['student', 'tutor', 'admin'], true), 403);

        $user->load('studentProfile.grade');

        $accesses = LessonAccess::with(['subject:id,name', 'grade:id,name'])
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereIn('id', function ($query) use ($user) {
                $query->selectRaw('MAX(id)')
                    ->from('lesson_access')
                    ->where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->groupBy('subject_id', 'grade_id');
            })
            ->orderByDesc('updated_at')
            ->get();

        $lessonCounts = collect();

        if ($accesses->isNotEmpty()) {
            $lessonCounts = Lesson::query()
                ->selectRaw('grade_id, subject_id, COUNT(*) as total')
                ->where('is_active', true)
                ->where(function ($query) use ($accesses) {
                    foreach ($accesses as $access) {
                        $query->orWhere(function ($pair) use ($access) {
                            $pair->where('grade_id', $access->grade_id)
                                ->where('subject_id', $access->subject_id);
                        });
                    }
                })
                ->groupBy('grade_id', 'subject_id')
                ->get()
                ->keyBy(fn ($lesson) => $lesson->grade_id . ':' . $lesson->subject_id);
        }

        $courses = $accesses->map(function ($access) use ($lessonCounts) {
            $key = $access->grade_id . ':' . $access->subject_id;

            return [
                'id' => $access->id,
                'grade_id' => $access->grade_id,
                'subject_id' => $access->subject_id,
                'grade' => $access->grade?->name,
                'subject' => $access->subject?->name,
                'expires_at' => $access->expires_at,
                'lesson_count' => (int) ($lessonCounts->get($key)?->total ?? 0),
            ];
        })->values();

        $continueLearning = null;

        if ($accesses->isNotEmpty() && Schema::hasTable('lesson_progress')) {
            $progress = LessonProgress::with([
                'lesson:id,grade_id,subject_id,topic,title,vimeo_url,answer_vimeo_url,is_active',
                'lesson.grade:id,name',
                'lesson.subject:id,name',
            ])
                ->where('user_id', $user->id)
                ->whereNull('completed_at')
                ->whereHas('lesson', function ($query) use ($accesses) {
                    $query->where('is_active', true)
                        ->where(function ($pairs) use ($accesses) {
                            foreach ($accesses as $access) {
                                $pairs->orWhere(function ($pair) use ($access) {
                                    $pair->where('grade_id', $access->grade_id)
                                        ->where('subject_id', $access->subject_id);
                                });
                            }
                        });
                })
                ->latest('last_viewed_at')
                ->first();

            if ($progress?->lesson) {
                $duration = (int) ($progress->duration_seconds ?? 0);
                $position = (int) $progress->position_seconds;

                $continueLearning = [
                    'lesson_id' => $progress->lesson->id,
                    'grade_id' => $progress->lesson->grade_id,
                    'subject_id' => $progress->lesson->subject_id,
                    'grade' => $progress->lesson->grade?->name,
                    'subject' => $progress->lesson->subject?->name,
                    'topic' => $progress->lesson->topic,
                    'title' => $progress->lesson->title,
                    'video_type' => $progress->video_type,
                    'position_seconds' => $position,
                    'duration_seconds' => $duration ?: null,
                    'progress_percent' => $duration > 0 ? min(100, (int) round(($position / $duration) * 100)) : 0,
                ];
            }
        }

        return response()->json([
            'student' => [
                'name' => $user->name,
                'grade_id' => $user->studentProfile?->grade_id,
                'grade' => $user->studentProfile?->grade?->name,
                'is_preview' => $user->role !== 'student',
            ],
            'stats' => [
                'active_courses' => $courses->count(),
                'available_lessons' => $courses->sum('lesson_count'),
                'tests_attempted' => 0,
                'average_score' => null,
            ],
            'courses' => $courses,
            'continue_learning' => $continueLearning,
            'recent_results' => [],
        ]);
    }
}
