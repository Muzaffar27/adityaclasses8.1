<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;

class GradeController extends Controller
{

    public function get(Request $request)
    {
        $subjectId = $request->query('subject_id');

        return Grade::withCount([
            'lessons as lessons_count' => function ($query) use ($subjectId) {
                if ($subjectId) {
                    $query->where('subject_id', $subjectId);
                }
            }
        ])
            ->orderByRaw('CAST(SUBSTRING(name, 7) AS UNSIGNED)')
            ->get();
    }
}
