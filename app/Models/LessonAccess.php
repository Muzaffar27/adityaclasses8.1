<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonAccess extends Model
{
    protected $table = 'lesson_access';

    protected $fillable = [
        'subject_id',
        'grade_id',
        'user_id',
        'status'
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
