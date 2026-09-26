<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'video_type',
        'position_seconds',
        'duration_seconds',
        'completed_at',
        'last_viewed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
