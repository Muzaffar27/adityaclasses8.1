<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'grade_id',
        'subject_id',
        'topic',
        'title',
        'part_number',
        'description',
        'vimeo_url',
        'duration',
        'pdf_resource',
        'is_active'
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function accesses()
    {
        // This connects your Lesson to the lesson_access table
        return $this->hasMany(LessonAccess::class, 'lesson_id');
    }
}
