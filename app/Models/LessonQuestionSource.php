<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonQuestionSource extends Model
{
    protected $guarded = [];

    protected $casts = [
        'revision' => 'integer',
        'question_count' => 'integer',
        'rejected_count' => 'integer',
        'skipped_count' => 'integer',
        'attempts' => 'integer',
        'force_reprocess' => 'boolean',
        'question_ids' => 'array',
        'requested_at' => 'datetime',
        'claimed_at' => 'datetime',
        'processed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];
}
