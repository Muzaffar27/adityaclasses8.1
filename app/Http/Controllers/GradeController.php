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

        return Grade::query()
            ->when($subjectId, function ($q) use ($subjectId) {
                $q->whereHas('lessons', fn($l) => $l->where('subject_id', $subjectId));
            })
            ->withCount([
                'lessons as lessons_count' => fn($q) =>
                $subjectId ? $q->where('subject_id', $subjectId) : $q
            ])
            ->having('lessons_count', '>', 0)
            ->orderByRaw('CAST(SUBSTRING(name, 7) AS UNSIGNED)')
            ->get();
    }
}
