<?php

namespace App\Services;

use App\Models\LessonProgress;
use App\Models\StudentLearningDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentLearningActivityService
{
    public const TIMEZONE = 'Indian/Mauritius';
    public const MINIMUM_ACTIVE_SECONDS = 600;
    public const MAX_HEARTBEAT_GAP_SECONDS = 45;

    public function ready(): bool
    {
        return Schema::hasTable('student_learning_days') && Schema::hasColumns('lesson_progress', [
            'activity_session_id', 'activity_observed_at', 'activity_position_seconds', 'activity_active',
        ]);
    }

    public function recordLogin(User $user): void
    {
        if ($user->role !== 'student' || !$this->ready()) return;

        DB::transaction(function () use ($user) {
            $now = now();
            $day = $this->lockedDay($user->id, $now->copy()->timezone(self::TIMEZONE)->toDateString());
            $day->first_login_at ??= $now;
            $day->last_login_at = $now;
            $day->login_count++;
            $day->save();
        });
    }

    // Called inside the progress transaction, with the progress row already locked.
    public function recordActivity(LessonProgress $progress, array $input, bool $sourceChanged = false): void
    {
        if (!$this->ready()) return;

        $now = now();
        $session = $input['activity_session_id'] ?? null;
        $position = (int) round($input['position_seconds'] ?? 0);
        $active = (bool) ($input['activity_active'] ?? false);
        $previous = $progress->activity_observed_at;
        $elapsed = $previous ? max(0, $previous->diffInSeconds($now, false)) : 0;
        $advance = $position - (int) $progress->activity_position_seconds;
        $questionPdf = in_array($progress->video_type, ['pdf_question', 'pdf_question2'], true);

        // Credit contiguous observations only; the final stop can credit its preceding active interval.
        if (!$sourceChanged && $session && $session === $progress->activity_session_id
            && $progress->activity_active && $elapsed > 0 && $elapsed <= self::MAX_HEARTBEAT_GAP_SECONDS
            && ($questionPdf || ($advance > 0 && $advance <= $elapsed * 2 + 2))) {
            $seconds = $questionPdf ? $elapsed : min($elapsed, $advance);
            $this->creditActivity($progress->user_id, $now->copy()->subSeconds($seconds), $now,
                $questionPdf ? 'question_pdf_seconds' : 'playback_seconds');
        }

        $progress->activity_session_id = $session;
        $progress->activity_observed_at = $session ? $now : null;
        $progress->activity_position_seconds = $session ? $position : null;
        $progress->activity_active = $session && $active;
    }

    private function creditActivity(int $userId, Carbon $start, Carbon $end, string $counter): void
    {
        while ($start->lessThan($end)) {
            $localStart = $start->copy()->timezone(self::TIMEZONE);
            $boundary = $localStart->copy()->startOfDay()->addDay()->timezone($end->timezone);
            $segmentEnd = $boundary->lessThan($end) ? $boundary : $end;
            $day = $this->lockedDay($userId, $localStart->toDateString());
            // Overlapping tabs/resources may credit each wall-clock second only once.
            $creditedStart = $day->last_credited_at && $day->last_credited_at->greaterThan($start)
                ? $day->last_credited_at : $start;
            $seconds = max(0, $creditedStart->diffInSeconds($segmentEnd, false));
            if ($seconds > 0) {
                $day->{$counter} += $seconds;
                $day->last_credited_at = $segmentEnd;
                $day->save();
            }
            $start = $segmentEnd->copy();
        }
    }

    private function lockedDay(int $userId, string $date): StudentLearningDay
    {
        $day = StudentLearningDay::firstOrCreate(['user_id' => $userId, 'activity_date' => $date]);
        return StudentLearningDay::whereKey($day->id)->lockForUpdate()->firstOrFail();
    }

    public function summary(int $userId, ?string $from = null, ?string $to = null): array
    {
        $today = now()->timezone(self::TIMEZONE)->startOfDay();
        $end = $to ? Carbon::parse($to, self::TIMEZONE)->startOfDay() : $today->copy();
        $start = $from ? Carbon::parse($from, self::TIMEZONE)->startOfDay() : $end->copy()->subDays(29);
        $result = [
            'available' => $this->ready(), 'timezone' => self::TIMEZONE,
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            'minimum_active_seconds' => self::MINIMUM_ACTIVE_SECONDS,
            'active_days' => 0, 'playback_seconds' => 0, 'today_playback_seconds' => 0,
            'question_pdf_seconds' => 0, 'today_question_pdf_seconds' => 0,
            'today_active' => false, 'completed_videos' => 0, 'completed_lessons' => 0,
        ];
        if (!$result['available']) return $result;

        $days = StudentLearningDay::where('user_id', $userId)->whereBetween('activity_date', [
            $start->toDateString(), $end->toDateString(),
        ]);
        $result['active_days'] = (clone $days)->where(function ($query) {
            $query->where('playback_seconds', '>=', self::MINIMUM_ACTIVE_SECONDS)
                ->orWhere('question_pdf_seconds', '>=', self::MINIMUM_ACTIVE_SECONDS);
        })->count();
        $result['playback_seconds'] = (int) $days->sum('playback_seconds');
        $result['question_pdf_seconds'] = (int) $days->sum('question_pdf_seconds');
        $todayRow = StudentLearningDay::where('user_id', $userId)->where('activity_date', $today->toDateString())->first();
        $result['today_playback_seconds'] = $todayRow?->playback_seconds ?? 0;
        $result['today_question_pdf_seconds'] = $todayRow?->question_pdf_seconds ?? 0;
        $result['today_active'] = max($result['today_playback_seconds'], $result['today_question_pdf_seconds']) >= self::MINIMUM_ACTIVE_SECONDS;

        $completed = LessonProgress::with('lesson')->where('user_id', $userId)
            ->whereIn('video_type', ['lesson', 'answer'])
            ->where('completed_at', '>=', $start->copy()->timezone(config('app.timezone')))
            ->where('completed_at', '<', $end->copy()->addDay()->timezone(config('app.timezone')))->get()
            ->filter(fn ($row) => app(LessonProgressService::class)->matchesCurrentSource($row));
        $result['completed_videos'] = $completed->count();
        $result['completed_lessons'] = $completed->where('video_type', 'lesson')->count();
        return $result;
    }
}
