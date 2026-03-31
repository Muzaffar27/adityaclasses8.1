<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use App\Models\LessonAccess;

class LessonController extends Controller
{

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
            ])->first();
        }

        // 3. Return structured response
        return response()->json([
            'lessons' => $lessons,
            'access' => [
                'has_access' => $access && $access->status === 'accepted',
                'status' => $access->status ?? null,
            ]
        ]);
    }
}
