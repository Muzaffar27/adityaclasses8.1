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

        $incoming = collect($request->all())
            ->unique(fn($item) => $item['subject_id'] . '-' . $item['grade_id']);

        // 🔍 get already approved access
        $existingApproved = DB::table('lesson_access')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->get()
            ->map(fn($item) => $item->subject_id . '-' . $item->grade_id)
            ->toArray();

        // ❌ remove approved ones from request
        $filtered = $incoming->reject(function ($req) use ($existingApproved) {
            return in_array($req['subject_id'] . '-' . $req['grade_id'], $existingApproved);
        });

        $data = $filtered->map(function ($req) use ($userId) {
            return [
                'user_id' => $userId,
                'subject_id' => $req['subject_id'],
                'grade_id' => $req['grade_id'],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->values()->toArray();

        if (!empty($data)) {
            DB::table('lesson_access')->upsert(
                $data,
                ['user_id', 'subject_id', 'grade_id'],
                ['status', 'updated_at']
            );
        }

        return response()->json([
            'message' => 'Request processed'
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
