<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\LessonAccess;

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

    public function accept(Request $request)
    {
        DB::table('lesson_access')
            ->where('id', $request->id)
            ->update([
                'status' => 'accepted',
                'updated_at' => now()
            ]);

        return response()->json(['message' => 'Accepted']);
    }

    public function acceptMultiple(Request $request)
    {
        LessonAccess::whereIn('id', $request->ids)
            ->update(['status' => 'accepted']);

        return response()->json(['message' => 'Accepted']);
    }

    public function refuseMultiple(Request $request)
    {
        LessonAccess::whereIn('id', $request->ids)
            ->update(['status' => 'refused']);

        return response()->json(['message' => 'Refused']);
    }

    public function refuse(Request $request)
    {
        DB::table('lesson_access')
            ->where('id', $request->id)
            ->update([
                'status' => 'refused',
                'updated_at' => now()
            ]);

        return response()->json(['message' => 'Refused']);
    }

    public function listRequests()
    {
        return DB::table('lesson_access')
            ->join('users', 'lesson_access.user_id', '=', 'users.id')
            ->join('lessons', 'lesson_access.lesson_id', '=', 'lessons.id')
            ->select(
                'lesson_access.id',
                'lesson_access.status',
                'lessons.id as lesson_id',
                'users.id as student_id',
                'users.name as student_name',
                'lessons.title as lesson_title'
            )
            ->where('lesson_access.status', 'pending')
            ->latest('lesson_access.created_at')
            ->get();
    }
}
