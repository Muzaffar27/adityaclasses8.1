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
        'status',
        'duration_months',
        'requested_price',
        'expires_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'duration_months' => 3,
        'requested_price' => 0,
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'requested_price' => 'decimal:2',
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
