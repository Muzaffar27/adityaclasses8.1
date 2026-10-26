<?php

namespace App\Observers;

use App\Models\Lesson;
use App\Services\LessonQuestionBankService;

class LessonQuestionBankObserver
{
    public function saved(Lesson $lesson): void
    {
        if ($lesson->wasRecentlyCreated || $lesson->wasChanged(['question_pdf_path', 'question_pdf_2_path', 'grade_id', 'subject_id', 'topic', 'is_active'])) {
            app(LessonQuestionBankService::class)->synchronize($lesson);
        }
    }

    public function deleted(Lesson $lesson): void
    {
        app(LessonQuestionBankService::class)->synchronize($lesson, true);
    }
}
