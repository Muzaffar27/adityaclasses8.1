<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAssessmentEnrolment extends Model
{
    protected $guarded = [];
    protected $casts = ['enabled' => 'boolean', 'baseline' => 'array', 'issues' => 'array', 'needs_reconcile' => 'boolean',
        'activated_at' => 'datetime', 'last_reconciled_at' => 'datetime'];
}
