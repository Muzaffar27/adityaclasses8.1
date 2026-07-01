<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use App\Models\LessonAccess;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{

    public function all()
    {
        return Lesson::with(['grade', 'subject'])
            ->orderBy('topic')
            ->get();
    }

    // LIST
    public function index(Request $request)
    {
        return Lesson::with(['grade', 'subject'])
            ->when($request->grade_id, fn($q) => $q->where('grade_id', $request->grade_id))
            ->when($request->subject_id, fn($q) => $q->where('subject_id', $request->subject_id))
            ->get();
    }

    // CREATE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'grade_id' => 'required',
            'subject_id' => 'required',
            'title' => 'required',
            'topic' => 'nullable',
            'sub_topic' => 'nullable',
            'part_number' => 'nullable',
            'description' => 'nullable',
            'vimeo_url' => 'nullable',
            'duration' => 'nullable',
        ]);

        return Lesson::create($validated);
    }

    // UPDATE
    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($request->all());

        return $lesson;
    }

    public function renameTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
            'old_topic' => 'required|string',
            'new_topic' => 'required|string|max:255',
        ]);

        $updated = Lesson::whereIn('id', $validated['lesson_ids'])
            ->where('topic', $validated['old_topic'])
            ->update(['topic' => $validated['new_topic']]);

        return response()->json([
            'message' => 'Topic renamed',
            'updated' => $updated,
        ]);
    }

    public function moveTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
            'grade_id' => 'required|integer|exists:grades,id',
            'subject_id' => 'required|integer|exists:subjects,id',
        ]);

        $updated = Lesson::whereIn('id', $validated['lesson_ids'])
            ->update([
                'grade_id' => $validated['grade_id'],
                'subject_id' => $validated['subject_id'],
            ]);

        return response()->json([
            'message' => 'Topic moved',
            'updated' => $updated,
        ]);
    }

    public function copyTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
            'grade_id' => 'required|integer|exists:grades,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'new_topic' => 'nullable|string|max:255',
        ]);

        $topic = isset($validated['new_topic']) ? trim($validated['new_topic']) : null;

        $lessons = Lesson::whereIn('id', $validated['lesson_ids'])
            ->orderBy('part_number')
            ->orderBy('id')
            ->get();

        $copied = DB::transaction(function () use ($lessons, $validated, $topic) {
            return $lessons->map(function (Lesson $lesson) use ($validated, $topic) {
                $copy = $lesson->replicate();
                $copy->grade_id = $validated['grade_id'];
                $copy->subject_id = $validated['subject_id'];
                if ($topic) {
                    $copy->topic = $topic;
                }
                $copy->save();

                return $copy;
            });
        });

        return response()->json([
            'message' => 'Topic copied',
            'copied' => $copied->count(),
        ], 201);
    }

    public function deleteTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
        ]);

        $deleted = Lesson::whereIn('id', $validated['lesson_ids'])->delete();

        return response()->json([
            'message' => 'Topic deleted',
            'deleted' => $deleted,
        ]);
    }

    // DELETE
    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return response()->json(['message' => 'Deleted']);
    }
    /**
     * Get all lessons for a specific grade and subject
     */
    public function get(Request $request)
    {
        $userId = auth()->id();

        // 1. Get lessons (NO access logic here anymore)
        $lessons = Lesson::where('subject_id', $request->subject_id)
            ->where('is_active', 1)
            ->where('grade_id', $request->grade_id)
            ->get();

        // 2. Get access for this subject + grade
        $access = null;

        if ($userId) {
            $access = LessonAccess::where([
                'user_id' => $userId,
                'subject_id' => $request->subject_id,
                'grade_id' => $request->grade_id,
            ])
                ->orderByRaw("CASE status WHEN 'accepted' THEN 0 WHEN 'pending' THEN 1 WHEN 'refused' THEN 2 ELSE 3 END")
                ->latest('updated_at')
                ->first();
        }

        // 3. Return structured response
        return response()->json([
            'lessons' => $lessons,
            'access' => [
                'has_access' => $access
                    && $access->status === 'accepted'
                    && (!$access->expires_at || $access->expires_at->isFuture()),
                'status' => $access->status ?? null,
                'expires_at' => $access->expires_at ?? null,
            ]
        ]);
    }

    public function myCourses()
    {
        return LessonAccess::with(['subject', 'grade'])
            ->where('user_id', auth()->id())
            ->where('status', 'accepted')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->select('lesson_access.*')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('lesson_access')
                    ->where('user_id', auth()->id())
                    ->where('status', 'accepted')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->groupBy('subject_id', 'grade_id');
            })
            ->get();
    }
}
