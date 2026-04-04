<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{

    public function getStudents()
    {
        return User::where('role', 'student')->get();
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
