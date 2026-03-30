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

    protected $guarded = [];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
