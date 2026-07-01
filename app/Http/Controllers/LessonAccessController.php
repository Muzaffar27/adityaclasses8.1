<?php

namespace App\Http\Controllers;

use App\Models\LessonAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonAccessController extends Controller
{
    private const VALID_DURATIONS = [3, 6, 9];

    public function count(Request $request)
    {
        return response()->json([
            'count' => LessonAccess::where('status', 'pending')->count()
        ]);
    }

    public function request(Request $request)
    {
        $userId = auth()->id();
        $requests = $this->normalizedAccessRequests($request);

        if ($requests->isEmpty()) {
            return response()->json([
                'message' => 'No valid access request found'
            ], 422);
        }

        DB::transaction(function () use ($requests, $userId) {
            foreach ($requests as $accessRequest) {
                $query = LessonAccess::where([
                    'user_id' => $userId,
                    'subject_id' => $accessRequest['subject_id'],
                    'grade_id' => $accessRequest['grade_id'],
                ]);

                if ((clone $query)
                    ->where('status', 'accepted')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->exists()) {
                    continue;
                }

                $existing = (clone $query)
                    ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'refused' THEN 1 ELSE 2 END")
                    ->first();

                if ($existing) {
                    $existing->update([
                        'status' => 'pending',
                        'duration_months' => $accessRequest['duration_months'],
                        'requested_price' => $accessRequest['requested_price'],
                        'expires_at' => null,
                    ]);
                    continue;
                }

                LessonAccess::create([
                    'user_id' => $userId,
                    'subject_id' => $accessRequest['subject_id'],
                    'grade_id' => $accessRequest['grade_id'],
                    'status' => 'pending',
                    'duration_months' => $accessRequest['duration_months'],
                    'requested_price' => $accessRequest['requested_price'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Request processed'
        ]);
    }

    public function accept(Request $request)
    {
        $access = LessonAccess::findOrFail($request->id);

        LessonAccess::where([
            'user_id' => $access->user_id,
            'subject_id' => $access->subject_id,
            'grade_id' => $access->grade_id,
        ])->update([
            'status' => 'accepted',
            'duration_months' => $access->duration_months,
            'requested_price' => $access->requested_price,
            'expires_at' => now()->addMonths($access->duration_months),
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Accepted']);
    }

    public function acceptMultiple(Request $request)
    {
        LessonAccess::whereIn('id', $request->ids)
            ->get()
            ->each(function (LessonAccess $access) {
                LessonAccess::where([
                    'user_id' => $access->user_id,
                    'subject_id' => $access->subject_id,
                    'grade_id' => $access->grade_id,
                ])->update([
                    'status' => 'accepted',
                    'duration_months' => $access->duration_months,
                    'requested_price' => $access->requested_price,
                    'expires_at' => now()->addMonths($access->duration_months),
                    'updated_at' => now()
                ]);
            });

        return response()->json(['message' => 'Accepted']);
    }

    public function refuseMultiple(Request $request)
    {
        LessonAccess::whereIn('id', $request->ids)
            ->update([
                'status' => 'refused',
                'updated_at' => now()
            ]);

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

    public function destroy($id)
    {
        LessonAccess::findOrFail($id)->delete();

        return response()->json(['message' => 'Removed']);
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
                'lesson_access.duration_months',
                'lesson_access.requested_price',
                'lesson_access.expires_at',
                'users.id as student_id',
                'users.name as student_name',
                'subjects.name as subject_name',
                'grades.name as grade_name'
            )
            ->where('lesson_access.status', 'pending')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('lesson_access as accepted_access')
                    ->whereColumn('accepted_access.user_id', 'lesson_access.user_id')
                    ->whereColumn('accepted_access.subject_id', 'lesson_access.subject_id')
                    ->whereColumn('accepted_access.grade_id', 'lesson_access.grade_id')
                    ->where('accepted_access.status', 'accepted')
                    ->where(function ($query) {
                        $query->whereNull('accepted_access.expires_at')
                            ->orWhere('accepted_access.expires_at', '>', now());
                    });
            })
            ->latest('lesson_access.created_at')
            ->get();
    }

    private function normalizedAccessRequests(Request $request)
    {
        $payload = $request->all();

        $items = isset($payload['subject_id'], $payload['grade_id'])
            ? [$payload]
            : array_values($payload);

        return collect($items)
            ->filter(fn($item) => is_array($item) && isset($item['subject_id'], $item['grade_id']))
            ->map(fn($item) => [
                'subject_id' => (int) $item['subject_id'],
                'grade_id' => (int) $item['grade_id'],
                'duration_months' => (int) ($item['duration_months'] ?? 3),
                'requested_price' => round((float) ($item['requested_price'] ?? 0), 2),
            ])
            ->filter(fn($item) => $item['subject_id'] > 0 && $item['grade_id'] > 0)
            ->filter(fn($item) => in_array($item['duration_months'], self::VALID_DURATIONS, true))
            ->unique(fn($item) => $item['subject_id'] . '-' . $item['grade_id'])
            ->values();
    }
}
