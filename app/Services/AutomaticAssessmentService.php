<?php

namespace App\Services;

use App\Models\{AutomaticAssessmentBatch, Lesson, LessonAccess, LessonProgress, StudentAssessmentEnrolment, StudentTopicCompletion, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AutomaticAssessmentService
{
    public function __construct(private SavedTestPaperService $papers, private LessonProgressService $progress) {}

    public function ensureReady(): void
    {
        foreach (['student_assessment_enrolments', 'student_topic_completions', 'automatic_assessment_batches', 'lesson_progress'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Automatic assessment setup is pending.');
        }
        $this->papers->ensureReady();
    }

    public function enrol(User $actor, User $student, int $grade, int $subject): StudentAssessmentEnrolment
    {
        $this->papers->staff($actor); $this->ensureReady();
        abort_unless(in_array($student->role, ['student', 'tutor', 'admin'], true), 422, 'Choose a learning account.');
        return DB::transaction(function () use ($actor, $student, $grade, $subject) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $existing = StudentAssessmentEnrolment::where('student_id', $student->id)->where('grade_id', $grade)->where('subject_id', $subject)->lockForUpdate()->first();
            if ($existing) return $existing;
            abort_unless($this->hasAccess($student->id, $grade, $subject), 403, 'Active course access is required.');
            return StudentAssessmentEnrolment::create(['student_id' => $student->id, 'grade_id' => $grade, 'subject_id' => $subject,
                'enabled_by' => $actor->id, 'activated_at' => now(), 'baseline' => [], 'enabled' => true]);
        }, 3);
    }

    /** Register learning automatically; generation stays in the background worker. */
    public function trackCourse(User $learner, int $grade, int $subject): ?StudentAssessmentEnrolment
    {
        if (!in_array($learner->role, ['student', 'tutor', 'admin'], true) || !$this->hasAccess($learner->id, $grade, $subject)) return null;
        return StudentAssessmentEnrolment::firstOrCreate(
            ['student_id' => $learner->id, 'grade_id' => $grade, 'subject_id' => $subject],
            ['enabled_by' => $learner->id, 'activated_at' => now(), 'baseline' => [], 'enabled' => true, 'needs_reconcile' => true]
        );
    }

    /** Bounded recovery also finds completions recorded before this workflow existed. */
    public function discoverCourses(int $limit): void
    {
        $access = LessonAccess::query()->selectRaw('1')->active()->whereColumn('lesson_access.user_id', 'p.user_id')
            ->whereColumn('lesson_access.grade_id', 'l.grade_id')->whereColumn('lesson_access.subject_id', 'l.subject_id')->toBase();
        $courses = DB::table('lesson_progress as p')->join('lessons as l', 'l.id', '=', 'p.lesson_id')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.video_type', 'lesson')->whereNotNull('p.completed_at')->where('l.is_active', true)
            ->whereIn('u.role', ['student', 'tutor', 'admin'])
            ->where(fn ($q) => $q->whereIn('u.role', ['tutor', 'admin'])->orWhereExists($access))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('student_assessment_enrolments as e')
                ->whereColumn('e.student_id', 'p.user_id')->whereColumn('e.grade_id', 'l.grade_id')->whereColumn('e.subject_id', 'l.subject_id'))
            ->select('p.user_id', 'l.grade_id', 'l.subject_id')->distinct()->orderBy('p.user_id')->orderBy('l.grade_id')->orderBy('l.subject_id')->limit($limit)->get();
        foreach ($courses as $course) $this->trackCourse(User::findOrFail($course->user_id), $course->grade_id, $course->subject_id);
    }

    /** Only current-source primary videos count. Missing primary videos block that topic. */
    private function completedTopics(int $student, int $grade, int $subject): array
    {
        $lessons = Lesson::where('grade_id', $grade)->where('subject_id', $subject)->where('is_active', true)->orderBy('id')->get();
        $rows = LessonProgress::where('user_id', $student)->whereIn('lesson_id', $lessons->pluck('id'))->where('video_type', 'lesson')
            ->whereNotNull('completed_at')->get()->keyBy('lesson_id');
        $result = [];
        foreach ($lessons->groupBy('topic') as $topic => $parts) {
            if (trim($topic) === '') continue;
            $evidence = []; $completed = null;
            foreach ($parts as $lesson) {
                $row = $rows->get($lesson->id); $source = $this->progress->videoSource($lesson->vimeo_url);
                if (!$source || !$row || !hash_equals($source, (string) $row->video_source)) continue 2;
                $evidence[] = ['lesson_id' => $lesson->id, 'video_source' => $source];
                if ($completed === null || $row->completed_at->greaterThan($completed)) $completed = $row->completed_at;
            }
            $result[] = ['topic' => (string) $topic, 'topic_key' => hash('sha256', (string) $topic),
                'completed_at' => $completed->toISOString(), 'evidence' => $evidence];
        }
        usort($result, fn ($a, $b) => [$a['completed_at'], $a['topic_key']] <=> [$b['completed_at'], $b['topic_key']]);
        return $result;
    }

    public function reconcile(int $id): array
    {
        $this->ensureReady();
        $initial = StudentAssessmentEnrolment::findOrFail($id);
        return DB::transaction(function () use ($id, $initial) {
            $student = User::whereKey($initial->student_id)->lockForUpdate()->firstOrFail();
            $enrolment = StudentAssessmentEnrolment::whereKey($id)->lockForUpdate()->firstOrFail();
            if (!$enrolment->enabled) return ['status' => 'disabled'];
            $this->papers->lockCourseForGeneration($enrolment->grade_id, $enrolment->subject_id);
            if (!in_array($student->role, ['student', 'tutor', 'admin'], true) || !$this->hasAccess($student->id, $enrolment->grade_id, $enrolment->subject_id)) {
                $this->checked($enrolment, 'waiting_access');
                return ['status' => 'waiting_access'];
            }
            $completed = $this->completedTopics($student->id, $enrolment->grade_id, $enrolment->subject_id);
            $events = StudentTopicCompletion::where('enrolment_id', $id)->get();
            $known = $events->map(fn ($e) => ['topic_key' => $e->topic_key, 'evidence' => $e->evidence])->all();
            $renamed = [];
            foreach ($completed as $topic) {
                if (in_array($topic['topic_key'], array_column($known, 'topic_key'), true)) continue;
                // Names are not stable IDs in this app. A renamed/split topic must not reuse tested lessons.
                foreach ($known as $old) if (array_intersect(array_column($old['evidence'], 'lesson_id'), array_column($topic['evidence'], 'lesson_id'))) {
                    $renamed[] = $topic['topic']; continue 2;
                }
                StudentTopicCompletion::create(['id' => (string) Str::uuid(), 'enrolment_id' => $id,
                    'topic_key' => $topic['topic_key'], 'topic' => $topic['topic'], 'completed_at' => $topic['completed_at'], 'evidence' => $topic['evidence']]);
                $known[] = $topic;
            }
            $unassigned = StudentTopicCompletion::where('enrolment_id', $id)->whereNull('automatic_batch_id')->orderBy('completed_at')->orderBy('topic_key')->get();
            foreach ($unassigned->chunk(3) as $three) {
                if ($three->count() !== 3) break;
                $batch = AutomaticAssessmentBatch::create(['id' => (string) Str::uuid(), 'enrolment_id' => $id,
                    'sequence' => (int) AutomaticAssessmentBatch::where('enrolment_id', $id)->max('sequence') + 1,
                    'topics' => $three->pluck('topic')->all(), 'status' => 'pending']);
                StudentTopicCompletion::whereIn('id', $three->pluck('id'))->update(['automatic_batch_id' => $batch->id]);
            }
            // One issuance per enrolment/pass; waiting batches never consume the next topic batch.
            $batch = AutomaticAssessmentBatch::where('enrolment_id', $id)->whereNull('paper_id')->where('status', '!=', 'cancelled')
                ->orderBy('attempts')->orderBy('sequence')->first();
            if ($batch) {
                $batch->increment('attempts');
                $captured = StudentTopicCompletion::where('automatic_batch_id', $batch->id)->get();
                $current = array_column($completed, null, 'topic_key');
                $changed = $captured->contains(fn ($e) => !isset($current[$e->topic_key]) || $current[$e->topic_key]['evidence'] !== $e->evidence);
                if ($changed) {
                    $batch->update(['status' => 'waiting_curriculum', 'details' => ['message' => 'Lessons or video sources changed after this topic completion. Tutor review is required.']]);
                } else {
                    try {
                        $usedIds = DB::table('test_paper_questions')->join('test_papers', 'test_papers.id', '=', 'test_paper_questions.paper_id')
                            ->join('assessment_batches', 'assessment_batches.id', '=', 'test_papers.batch_id')
                            ->where('assessment_batches.student_id', $student->id)->where('assessment_batches.grade_id', $enrolment->grade_id)
                            ->where('assessment_batches.subject_id', $enrolment->subject_id)->where('assessment_batches.purpose', 'main')
                            ->distinct()->pluck('source_question_id')->all();
                        $paper = $this->papers->assignAutomatic($student, ['student_id' => $student->id, 'grade_id' => $enrolment->grade_id,
                            'subject_id' => $enrolment->subject_id, 'purpose' => 'main', 'batch_key' => $batch->id, 'topics' => $batch->topics], $usedIds);
                        $batch->update(['status' => 'issued', 'paper_id' => $paper->id, 'details' => null]);
                    } catch (ValidationException $e) {
                        $batch->update(['status' => 'waiting_questions', 'details' => ['errors' => $e->errors()]]);
                    } catch (HttpException $e) {
                        if (!in_array($e->getStatusCode(), [403, 409], true)) throw $e;
                        $batch->update(['status' => 'waiting_retry', 'details' => ['message' => $e->getMessage()]]);
                    }
                }
            }
            $this->checked($enrolment, $batch?->status ?? 'waiting_topics', $renamed ? ['renamed_topics_need_review' => $renamed] : null);
            return ['status' => $batch?->status ?? 'waiting_topics', 'paper_id' => $batch?->paper_id,
                'topics_waiting' => StudentTopicCompletion::where('enrolment_id', $id)->whereNull('automatic_batch_id')->count(), 'renamed_topics_need_review' => $renamed];
        }, 3);
    }

    private function hasAccess(int $student, int $grade, int $subject): bool
    {
        if (User::whereKey($student)->whereIn('role', ['tutor', 'admin'])->exists()) return true;
        return LessonAccess::where('user_id', $student)->where('grade_id', $grade)->where('subject_id', $subject)->active()->exists();
    }

    private function checked(StudentAssessmentEnrolment $enrolment, string $status, ?array $issues = null): void
    {
        $enrolment->update(['needs_reconcile' => false, 'last_reconciled_at' => now(), 'last_status' => $status, 'issues' => $issues]);
    }
}
