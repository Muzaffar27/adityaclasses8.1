<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPaperAttempt extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'marked_at' => 'datetime',
        'score' => 'decimal:2', 'total_marks' => 'decimal:2'];
}
