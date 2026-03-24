<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Grade;

class GradeController extends Controller
{

    public function get()
    {
        return Grade::withCount('lessons')
            ->orderByRaw('CAST(SUBSTRING(name, 7) AS UNSIGNED)')
            ->get();
    }
}
