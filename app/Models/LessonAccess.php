<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonAccess extends Model
{
    // Tell Laravel to stop looking for "lesson_accesses"
    protected $table = 'lesson_access';

    protected $fillable = ['lesson_id', 'user_id', 'status'];
}
