<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;


class UserController extends Controller
{

    public function getStudents()
    {
        return User::where('role', 'student')->get();
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
