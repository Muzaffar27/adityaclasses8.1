<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use App\Models\LessonAccess;

class LessonController extends Controller
{

    public function get(Request $request)
    {
        $userId = auth()->id();

        // 1. Get lessons (NO access logic here anymore)
        $lessons = Lesson::where('subject_id', $request->subject_id)
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
