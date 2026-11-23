<?php

// Extends the synthetic fixture made by verify-saved-paper-concurrency.php; no application DB.
use App\Models\{Lesson, LessonAccess, LessonProgress, StudentAssessmentEnrolment, StudentTopicCompletion, TestPaper, User};
use App\Services\{AutomaticAssessmentService, LessonProgressService, SavedTestPaperService, TestPaperResponseService};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e instanceof RuntimeException ? $e->getMessage().PHP_EOL : get_class($e).PHP_EOL); exit(1); });
$database = config('database.connections.mysql.database');
if (!$app->environment('testing') || config('database.default') !== 'mysql' || !preg_match('/^assessment_phase2_check_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Use only a dedicated synthetic assessment_phase2_check_<12hex> MySQL database.');
}
$root = sys_get_temp_dir().'/'.$database;
if (!is_file($root.'/fixture.json')) throw new RuntimeException('Create the isolated concurrency fixture first.');
config(['filesystems.disks.local.root' => $root]);
if (!Schema::hasTable('lesson_progress')) (require database_path('migrations/2026_09_26_000002_create_lesson_progress_table.php'))->up();
if (!Schema::hasColumn('lesson_progress', 'video_source')) (require database_path('migrations/2026_10_01_000001_add_video_source_to_lesson_progress_table.php'))->up();
if (!Schema::hasTable('student_assessment_enrolments')) (require database_path('migrations/2026_11_12_000003_create_automatic_assessment_tracking.php'))->up();
if (!Schema::hasTable('test_paper_answers')) (require database_path('migrations/2026_11_12_000004_create_test_paper_responses.php'))->up();
if (is_file($root.'/automatic-flow-completed.json')) throw new RuntimeException('This fixture was already verified. Use a fresh isolated fixture; no reset or deletion is performed.');
$fixture = json_decode(file_get_contents($root.'/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$tutor = User::findOrFail($fixture['tutor_id']);
// The concurrency fixture's first student already saw every question. Use a fresh
// synthetic learner so the no-repeat main-paper rule remains enforced during this test.
$student = User::factory()->create(['role' => 'student']);
LessonAccess::create(['user_id' => $student->id, 'grade_id' => $fixture['input']['grade_id'],
    'subject_id' => $fixture['input']['subject_id'], 'status' => 'accepted']);
$automatic = app(AutomaticAssessmentService::class); $papers = app(SavedTestPaperService::class);
$enrolment = $automatic->enrol($tutor, $student, $fixture['input']['grade_id'], $fixture['input']['subject_id']);
$before = TestPaper::count();
foreach (Lesson::where('grade_id', $enrolment->grade_id)->where('subject_id', $enrolment->subject_id)->orderBy('id')->get() as $lesson) {
    Carbon::setTestNow(now()->addSeconds(2));
    LessonProgress::create(['user_id' => $student->id, 'lesson_id' => $lesson->id, 'video_type' => 'lesson',
        'video_source' => app(LessonProgressService::class)->videoSource($lesson->vimeo_url), 'completed_at' => now()]);
}
$result = $automatic->reconcile($enrolment->id);
if ($result['status'] !== 'issued' || TestPaper::count() !== $before + 1 || StudentTopicCompletion::where('enrolment_id', $enrolment->id)->count() !== 3) throw new RuntimeException('Automatic MariaDB generation failed: '.json_encode($result));
$id = $result['paper_id']; $papers->start($student, $id);
$responses = app(TestPaperResponseService::class);
$responses->save($student, $id, 0, [['number' => 1, 'answer' => '7', 'working' => "1 + 6\n= 7"]]);
$saved = $papers->payload($papers->findFor($student, $id), $student);
if ($saved['responses']['revision'] !== 1 || $saved['responses']['answers'][0]->answer !== '7') throw new RuntimeException('MariaDB answer persistence failed.');
$submitted = $responses->submit($student, $id, 1);
if ($submitted->status !== 'awaiting_review' || $submitted->attempt->score !== null) throw new RuntimeException('Submission did not remain unmarked.');
$responses->submit($student, $id, 1); $automatic->reconcile($enrolment->id);
if (TestPaper::count() !== $before + 1) throw new RuntimeException('Repeated reconciliation created a duplicate.');
$summary = ['database' => $database, 'new_automatic_papers' => 1, 'topic_completions' => 3,
    'answer_saved_and_resumed' => true, 'submission' => 'awaiting_review', 'score' => null, 'duplicates' => 0];
file_put_contents($root.'/automatic-flow-completed.json', json_encode($summary, JSON_PRETTY_PRINT));
echo json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL;
// Retain all synthetic records for inspection; no DROP, reset, rollback or deletion.
