<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subject;

class SubjectController extends Controller
{

    public function get()
    {
        return Subject::orderBy('name', 'asc')->get();
    }
}
