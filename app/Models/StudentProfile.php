<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $fillable = [
        'grade_id',
        'academic_year',
        'student_phone',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_report_consent_at',
        'must_change_password',
    ];

    protected $casts = [
        'guardian_report_consent_at' => 'datetime',
        'must_change_password' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
