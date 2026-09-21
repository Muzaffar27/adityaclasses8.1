<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class UserController extends Controller
{

    private const PHONE_RULE = 'regex:/^\+?[0-9\s().-]{7,25}$/';

    public function getStudents(Request $request)
    {
        $this->ensureTutor($request);

        $packages = Package::with(['grade', 'subject', 'items.grade', 'items.subject'])
            ->orderBy('name')
            ->get();

        $lessonCounts = Lesson::selectRaw('grade_id, subject_id, COUNT(*) as lesson_count')
            ->where('is_active', 1)
            ->groupBy('grade_id', 'subject_id')
            ->get()
            ->mapWithKeys(fn($row) => [
                $row->grade_id . '-' . $row->subject_id => (int) $row->lesson_count,
            ]);

        return User::select('id', 'name', 'email', 'role', 'created_at')
            ->with([
                'studentProfile.grade:id,name',
                'lessonAccess.grade',
                'lessonAccess.subject',
            ])
            ->where('role', 'student')
            ->orderBy('name')
            ->get()
            ->map(function ($student) use ($packages, $lessonCounts) {
                $student->lessonAccess->each(function ($access) use ($lessonCounts) {
                    $access->lesson_count = $lessonCounts->get(
                        $access->grade_id . '-' . $access->subject_id,
                        0
                    );
                });

                $acceptedAccessByKey = $student->lessonAccess
                    ->where('status', 'accepted')
                    ->filter(fn($access) => !$access->expires_at || $access->expires_at->isFuture())
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
                                    'expires_at' => $access->expires_at,
                                    'lesson_count' => $access->lesson_count,
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
            'student_phone' => ['nullable', 'string', 'max:32', self::PHONE_RULE],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'string', 'max:80'],
            'guardian_phone' => ['nullable', 'string', 'max:32', self::PHONE_RULE],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if ($user->role === 'student') {
                $profile = $user->studentProfile()->lockForUpdate()->first()
                    ?? $user->studentProfile()->make();

                $editableFields = [
                    'grade_id',
                    'academic_year',
                    'student_phone',
                    'guardian_name',
                    'guardian_relationship',
                ];

                foreach ($editableFields as $field) {
                    if (array_key_exists($field, $validated)) {
                        $profile->{$field} = $validated[$field];
                    }
                }

                if (array_key_exists('guardian_phone', $validated)) {
                    $currentPhone = trim((string) $profile->guardian_phone);
                    $requestedPhone = trim((string) ($validated['guardian_phone'] ?? ''));

                    if ($currentPhone !== '' && $requestedPhone !== $currentPhone) {
                        throw ValidationException::withMessages([
                            'guardian_phone' => [
                                'Only your tutor can change or remove the parent WhatsApp number once it is saved.',
                            ],
                        ]);
                    }

                    if ($currentPhone === '') {
                        $profile->guardian_phone = $requestedPhone !== '' ? $requestedPhone : null;
                    }
                }

                $profile->save();
            }
        });

        $user = $user->fresh()->loadMissing('studentProfile.grade:id,name');

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

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'password' => Hash::make($validated['password'])
            ]);

            if ($user->role === 'student') {
                $user->studentProfile()->updateOrCreate([], [
                    'must_change_password' => false,
                ]);
            }
        });

        $user = $user->fresh()->loadMissing('studentProfile.grade:id,name');

        return response()->json([
            'message' => 'Password updated successfully',
            'user' => $user,
        ]);
    }

    public function updateStudentProfile(Request $request, User $student)
    {
        $this->ensureTutor($request);
        abort_unless($student->role === 'student', 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($student->id)],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'student_phone' => ['nullable', 'string', 'max:32', self::PHONE_RULE],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'string', 'max:80'],
            'guardian_phone' => ['nullable', 'string', 'max:32', self::PHONE_RULE],
            'guardian_report_consent' => ['required', 'boolean'],
        ]);

        $existingConsentAt = $student->studentProfile?->guardian_report_consent_at;

        DB::transaction(function () use ($student, $validated, $existingConsentAt) {
            $student->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $student->studentProfile()->updateOrCreate([], [
                'grade_id' => $validated['grade_id'] ?? null,
                'academic_year' => $validated['academic_year'] ?? null,
                'student_phone' => $validated['student_phone'] ?? null,
                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_relationship' => $validated['guardian_relationship'] ?? null,
                'guardian_phone' => $validated['guardian_phone'] ?? null,
                'guardian_report_consent_at' => $validated['guardian_report_consent']
                    ? ($existingConsentAt ?? now())
                    : null,
            ]);
        });

        return response()->json([
            'message' => 'Student profile updated successfully',
            'student' => $student->fresh()->loadMissing('studentProfile.grade:id,name'),
        ]);
    }

    public function resetPassword(Request $request, User $student)
    {
        $this->ensureTutor($request);
        abort_unless($student->role === 'student', 404);

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
            . random_int(10, 99);

        DB::transaction(function () use ($student, $newPassword) {
            $student->update(['password' => Hash::make($newPassword)]);
            $student->studentProfile()->updateOrCreate([], [
                'must_change_password' => true,
            ]);
        });

        return response()->json([
            'message' => 'Password reset successfully',
            'password' => $newPassword
        ]);
    }

    private function ensureTutor(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['tutor', 'admin'], true), 403);
    }
}
