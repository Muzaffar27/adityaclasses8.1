<?php

namespace App\Observers;

use App\Models\LessonProgress;
use App\Models\StudentAssessmentEnrolment;
use Illuminate\Support\Facades\Schema;

class AssessmentProgressObserver
{
    public function saved(LessonProgress $progress): void
    {
        if ($progress->video_type !== 'lesson' || (!$progress->wasRecentlyCreated
            && !$progress->wasChanged(['completed_at', 'video_source']))) return;
        if (!Schema::hasTable('student_assessment_enrolments')) return;
        $lesson = $progress->lesson;
        if (!$lesson) return;
        if ($progress->completed_at && ($learner = \App\Models\User::find($progress->user_id))) {
            app(\App\Services\AutomaticAssessmentService::class)->trackCourse($learner, $lesson->grade_id, $lesson->subject_id);
        }
        // Durable outbox flag in the same transaction as progress; no PDF scanning here.
        StudentAssessmentEnrolment::where('student_id', $progress->user_id)->where('grade_id', $lesson->grade_id)
            ->where('subject_id', $lesson->subject_id)->where('enabled', true)->update(['needs_reconcile' => true]);
    }
}
