<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{

    public function get(Request $request)
    {
        $userId = auth()->id();

        $lessons = Lesson::where('subject_id', $request->subject_id)
            ->where('grade_id', $request->grade_id)
            ->when($userId, function ($query) use ($userId) {
                // Only check access if we actually have a user
                $query->withExists(['accesses as user_has_access' => function ($q) use ($userId) {
                    $q->where('user_id', $userId)->where('status', 'accepted');
                }]);
            }, function ($query) {
                // If no user, just set it to false
                $query->withExists(['accesses as user_has_access' => function ($q) {
                    $q->whereRaw('1 = 0');
                }]);
            })
            ->get();

        return $lessons;
    }
}
