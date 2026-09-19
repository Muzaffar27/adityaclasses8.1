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
        'sub_topic',
        'title',
        'part_number',
        'description',
        'vimeo_url',
        'answer_vimeo_url',
        'duration',
        'question_pdf_path',
        'question_pdf_2_path',
        'answer_pdf_path',
        'is_active'
    ];

    protected $guarded = [];

    protected $hidden = [
        'question_pdf_path',
        'question_pdf_2_path',
        'answer_pdf_path',
    ];

    protected $appends = [
        'has_question_pdf',
        'has_question_pdf_2',
        'has_answer_pdf',
    ];

    public function getHasQuestionPdfAttribute(): bool
    {
        return !empty($this->question_pdf_path);
    }

    public function getHasQuestionPdf2Attribute(): bool
    {
        return !empty($this->question_pdf_2_path);
    }

    public function getHasAnswerPdfAttribute(): bool
    {
        return !empty($this->answer_pdf_path);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
