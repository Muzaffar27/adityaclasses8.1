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
        'video_source',
        'position_seconds',
        'duration_seconds',
        'video_started_at',
        'completed_at',
        'last_viewed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'video_started_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'activity_observed_at' => 'datetime',
        'activity_active' => 'boolean',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
