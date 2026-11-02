<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionCorrection extends Model
{
    protected $primaryKey = 'question_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['original' => 'array', 'sources' => 'array', 'retry_sources' => 'array',
        'corrected_content' => 'array', 'attempts' => 'integer', 'flagged_at' => 'datetime', 'corrected_at' => 'datetime'];
}
