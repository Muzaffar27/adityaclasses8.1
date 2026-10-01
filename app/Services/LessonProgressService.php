<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Collection;

class LessonProgressService
{
    public const COMPLETION_THRESHOLD = 0.95;

    public function video(?LessonProgress $progress): array
    {
        $completed = (bool) $progress?->completed_at;
        $duration = (int) ($progress?->duration_seconds ?? 0);

        return [
            'status' => $completed ? 'completed' : ($progress ? 'in_progress' : 'not_started'),
            'percent' => $completed ? 100 : ($duration > 0
                ? min(94, (int) floor($progress->position_seconds / $duration * 100)) : 0),
        ];
    }

    public function summary(Lesson $lesson, Collection $rows): array
    {
        $rows = $rows->keyBy('video_type');
        $video = $this->video($rows->get('lesson'));
        $pdfs = [];
        foreach (['lesson', 'question', 'question2', 'answer'] as $type) {
            $pdfs[$type] = $rows->has('pdf_' . $type) ? 'viewed' : 'not_started';
        }

        // Supporting PDFs and answer videos never complete the main video.
        return [
            'status' => $lesson->vimeo_url ? $video['status'] : $pdfs['lesson'],
            'percent' => $lesson->vimeo_url ? $video['percent'] : null,
            'lesson_video' => $video,
            'answer_video' => $this->video($rows->get('answer')),
            'pdfs' => $pdfs,
        ];
    }

    public function forUser(Lesson $lesson, int $userId): array
    {
        return $this->summary($lesson, LessonProgress::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)->get());
    }
}
