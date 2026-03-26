<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonController extends Controller
{

    public function get(Request $request)
    {
        return Lesson::where('subject_id', $request->subject_id)
            ->where('grade_id', $request->grade_id)
            ->get();
    }
}
