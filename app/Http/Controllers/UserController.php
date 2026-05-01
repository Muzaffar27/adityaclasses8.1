<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;


class UserController extends Controller
{

    public function getStudents()
    {
        $packages = Package::with(['grade', 'subject', 'items.grade', 'items.subject'])
            ->orderBy('name')
            ->get();

        $lessonsByAccess = Lesson::select('id', 'grade_id', 'subject_id', 'topic', 'sub_topic', 'title', 'part_number', 'duration')
            ->where('is_active', 1)
            ->orderBy('topic')
            ->orderBy('part_number')
            ->get()
            ->groupBy(fn($lesson) => $lesson->grade_id . '-' . $lesson->subject_id);

        return User::with(['lessonAccess.grade', 'lessonAccess.subject'])
            ->where('role', 'student')
            ->orderBy('name')
            ->get()
            ->map(function ($student) use ($packages, $lessonsByAccess) {
                $student->lessonAccess->each(function ($access) use ($lessonsByAccess) {
                    $access->lessons = $lessonsByAccess
                        ->get($access->grade_id . '-' . $access->subject_id, collect())
                        ->values();
                });

                $acceptedAccessByKey = $student->lessonAccess
                    ->where('status', 'accepted')
                    ->keyBy(fn($access) => $access->grade_id . '-' . $access->subject_id);

                $student->package_access = $packages
                    ->map(function ($package) use ($acceptedAccessByKey) {
                        $items = $package->items->filter(fn($item) => $item->grade_id && $item->subject_id);

                        if ($items->isEmpty()) {
                            return null;
                        }

                        $matchedItems = $items
                            ->map(function ($item) use ($acceptedAccessByKey) {
                                $access = $acceptedAccessByKey->get($item->grade_id . '-' . $item->subject_id);

                                if (!$access) {
                                    return null;
                                }

                                return [
                                    'access_id' => $access->id,
                                    'grade_id' => $item->grade_id,
                                    'subject_id' => $item->subject_id,
                                    'grade_name' => $item->grade?->name,
                                    'subject_name' => $item->subject?->name,
                                    'status' => $access->status,
                                    'lesson_count' => $access->lessons?->count() ?? 0,
                                ];
                            })
                            ->filter()
                            ->values();

                        if ($matchedItems->isEmpty()) {
                            return null;
                        }

                        return [
                            'id' => $package->id,
                            'name' => $package->name,
                            'grade_name' => $package->grade?->name,
                            'subject_name' => $package->subject?->name,
                            'total_price' => $package->total_price,
                            'status' => $matchedItems->count() === $items->count() ? 'full' : 'partial',
                            'matched_items' => $matchedItems->count(),
                            'total_items' => $items->count(),
                            'items' => $matchedItems,
                        ];
                    })
                    ->filter()
                    ->values();

                return $student;
            });
    }

    public function updateUserInfo(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function updateUserPwd(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        // Check current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['Current password is incorrect']
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }

    public function resetPassword($id)
    {
        $student = User::findOrFail($id);

        // 🔥 easy words pool
        $words = [
            'apple',
            'banana',
            'orange',
            'grape',
            'mango',
            'sunshine',
            'rainbow',
            'rocket',
            'tiger',
            'lion',
            'panda',
            'dragon',
            'castle',
            'river',
            'forest',
            'cloud',
            'ocean',
            'breeze',
            'star',
            'moon',
            'coffee',
            'pizza',
            'burger',
            'cookie',
            'chocolate',
            'blue',
            'green',
            'yellow',
            'purple',
            'red',
            'happy',
            'smile',
            'laugh',
            'dance',
            'music',
            'summer',
            'winter',
            'spring',
            'autumn',
            'storm'
        ];

        $newPassword = Str::ucfirst($words[array_rand($words)])
            . $words[array_rand($words)]
            . rand(10, 99);

        $student->password = Hash::make($newPassword);
        $student->save();

        return response()->json([
            'message' => 'Password reset successfully',
            'password' => $newPassword
        ]);
    }
}
