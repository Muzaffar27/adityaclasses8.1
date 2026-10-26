<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionReview extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'content' => 'array', 'origins' => 'array', 'retry_sources' => 'array',
        'revision' => 'integer', 'review_cycles' => 'integer',
        'approved_at' => 'datetime', 'rejected_at' => 'datetime',
    ];
}
