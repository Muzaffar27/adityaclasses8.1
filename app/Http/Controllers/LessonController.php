<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;

class LessonController extends Controller
{

    public function get()
    {

        $lessons = Lesson::with(['subject', 'grade'])
            ->where('is_active', true)
            ->orderBy('subject_id')
            ->orderBy('grade_id')
            ->orderBy('part_number')
            ->get();

        return response()->json($lessons);
    }
}
