<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class LessonAccessController extends Controller
{

    public function request(Request $request)
    {

        $userId = auth()->id();

        DB::table('lesson_access')->updateOrInsert(
            [
                'lesson_id' => $request->lesson_id,
                'user_id' => $userId,
            ],
            [
                'status' => 'pending',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Request sent'
        ]);
    }
}
