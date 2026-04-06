<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\LessonAccess;

class LessonAccessController extends Controller
{

    public function count(Request $request)
    {
        return response()->json([
            'count' => LessonAccess::where('status', 'pending')->count()
        ]);
    }

    public function request(Request $request)
    {
        $userId = auth()->id();

        DB::table('lesson_access')->updateOrInsert(
            [
                'user_id' => $userId,
                'subject_id' => $request->subject_id,
                'grade_id' => $request->grade_id,
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
            ->join('subjects', 'lesson_access.subject_id', '=', 'subjects.id')
            ->join('grades', 'lesson_access.grade_id', '=', 'grades.id')
            ->select(
                'lesson_access.id',
                'lesson_access.status',
                'users.id as student_id',
                'users.name as student_name',
                'subjects.name as subject_name',
                'grades.name as grade_name'
            )
            ->where('lesson_access.status', 'pending')
            ->latest('lesson_access.created_at')
            ->get();
    }
}
