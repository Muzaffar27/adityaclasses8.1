<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{

    public function get()
    {
        return Subject::orderBy('name', 'asc')->get();
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name'
        ]);

        $subject = Subject::create([
            'name' => trim($validated['name'])
        ]);

        return response()->json($subject, 201);
    }
}
