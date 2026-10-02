<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class LessonProgressService
{
    public const COMPLETION_THRESHOLD = 0.95;
    public const DURATION_TOLERANCE_SECONDS = 2;
    public const MINIMUM_COMPLETION_SECONDS = 30;

    public function videoSource(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url === '' ? null : hash('sha256', $url);
    }

    public function completionDelay(int $duration): int
    {
        return min(self::MINIMUM_COMPLETION_SECONDS, max(5, (int) ceil($duration * 0.25)));
    }

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
        $video = $this->videoForSource($rows->get('lesson'), $this->videoSource($lesson->vimeo_url));
        $pdfs = [];
        foreach (['lesson', 'question', 'question2', 'answer'] as $type) {
            $pdfs[$type] = $rows->has('pdf_' . $type) ? 'viewed' : 'not_started';
        }

        // Supporting PDFs and answer videos never complete the main video.
        return [
            'status' => $lesson->vimeo_url ? $video['status'] : $pdfs['lesson'],
            'percent' => $lesson->vimeo_url ? $video['percent'] : null,
            'lesson_video' => $video,
            'answer_video' => $this->videoForSource($rows->get('answer'), $this->videoSource($lesson->answer_vimeo_url)),
            'pdfs' => $pdfs,
        ];
    }

    public function matchesCurrentSource(LessonProgress $progress): bool
    {
        $field = $progress->video_type === 'answer' ? 'answer_vimeo_url' : 'vimeo_url';
        $source = $this->videoSource($progress->lesson?->{$field});

        return $source !== null && $progress->video_source !== null
            && hash_equals($source, $progress->video_source);
    }

    private function videoForSource(?LessonProgress $progress, ?string $source): array
    {
        return $progress && $source !== null && hash_equals($source, (string) $progress->video_source)
            ? $this->video($progress)
            : $this->video(null);
    }

    public function forUser(Lesson $lesson, int $userId): array
    {
        return $this->summary($lesson, LessonProgress::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)->get());
    }

    public function recentActivity(int $userId, Collection $accesses): array
    {
        if ($accesses->isEmpty()) return [];

        $pdfFields = [
            'pdf_lesson' => 'lesson_pdf_path', 'pdf_question' => 'question_pdf_path',
            'pdf_question2' => 'question_pdf_2_path', 'pdf_answer' => 'answer_pdf_path',
        ];

        return LessonProgress::with(['lesson.grade:id,name', 'lesson.subject:id,name'])
            ->where('user_id', $userId)
            ->whereHas('lesson', function ($query) use ($accesses) {
                $query->where('is_active', true)->where(function ($pairs) use ($accesses) {
                    foreach ($accesses as $access) {
                        $pairs->orWhere(function ($pair) use ($access) {
                            $pair->where('grade_id', $access->grade_id)->where('subject_id', $access->subject_id);
                        });
                    }
                });
            })
            ->orderByDesc('last_viewed_at')->orderByDesc('id')
            ->lazy(100)
            ->filter(function ($row) use ($pdfFields) {
                if (in_array($row->video_type, ['lesson', 'answer'], true)) {
                    return $this->matchesCurrentSource($row);
                }
                $field = $pdfFields[$row->video_type] ?? null;
                $path = $field ? $row->lesson->{$field} : null;
                return $path && Storage::disk('local')->exists($path);
            })
            ->take(5)
            ->map(fn ($row) => [
                'id' => $row->id,
                'lesson_id' => $row->lesson_id,
                'grade_id' => $row->lesson->grade_id,
                'subject_id' => $row->lesson->subject_id,
                'title' => $row->lesson->title,
                'subject' => $row->lesson->subject?->name,
                'topic' => $row->lesson->topic,
                'resource_type' => $row->video_type,
                'last_viewed_at' => $row->last_viewed_at->toISOString(),
            ])->values()->all();
    }
}
