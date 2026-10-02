<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLearningDay extends Model
{
    protected $fillable = ['user_id', 'activity_date'];

    protected $casts = [
        'last_credited_at' => 'datetime',
        'first_login_at' => 'datetime',
        'last_login_at' => 'datetime',
        'playback_seconds' => 'integer',
        'question_pdf_seconds' => 'integer',
        'login_count' => 'integer',
    ];
}
