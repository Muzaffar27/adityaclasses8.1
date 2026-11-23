<?php

// Dedicated disposable-fixture database only. Never run against the application database.
use App\Models\AssessmentBatch;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonQuestionSource;
use App\Models\QuestionReview;
use App\Models\Subject;
use App\Models\TestPaper;
use App\Models\TestPaperAttempt;
use App\Models\TestPaperQuestion;
use App\Models\User;
use App\Services\SavedTestPaperService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.mysql.database');
if (!$app->environment('testing') || config('database.default') !== 'mysql'
    || !preg_match('/^assessment_phase2_check_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Use APP_ENV=testing and a dedicated assessment_phase2_check_<12 hex> MySQL database.');
}
$root = sys_get_temp_dir().'/'.$database;
config(['filesystems.disks.local.root' => $root]);
$mode = $argv[1] ?? 'verify';
if ($mode === 'issue') {
    file_put_contents($root.'/ready.'.getmypid(), 'ready');
    $deadline = microtime(true) + 20;
    while (!is_file($root.'/start')) {
        if (microtime(true) > $deadline) throw new RuntimeException('Concurrency barrier timed out.');
        usleep(10000);
    }
    $fixture = json_decode(file_get_contents($root.'/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
    $paper = app(SavedTestPaperService::class)->assign(User::findOrFail($fixture['tutor_id']), $fixture['input']);
    echo $paper->id;
    exit(0);
}
if ($mode !== 'verify') throw new RuntimeException('Expected verify or internal issue mode.');
if (DB::select('SHOW TABLES')) throw new RuntimeException('Verification requires a fresh empty dedicated database; existing data is never reset.');
if (!is_dir($root) && !mkdir($root, 0700, true)) throw new RuntimeException('Cannot create private fixture directory.');
foreach (['2014_10_12_000000_create_users_table.php', '2026_03_24_163241_create_grades_table.php',
    '2026_03_24_163313_create_subjects_table.php', '2026_03_24_163323_create_lessons_table.php',
    '2026_03_26_185603_create_lesson_access_table.php', '2026_06_04_000001_add_duration_price_and_expiry_to_lesson_access_table.php',
    '2026_10_19_000001_create_lesson_question_sources_table.php', '2026_10_19_000002_create_question_reviews_table.php',
    '2026_11_01_000001_create_question_corrections_table.php', '2026_11_12_000001_create_saved_test_papers.php'] as $migration) {
    (require database_path('migrations/'.$migration))->up();
}
Schema::table('users', fn ($table) => $table->string('role')->default('student'));
Schema::table('lessons', function ($table) {
    foreach (['question_pdf_path', 'question_pdf_2_path', 'lesson_pdf_path', 'answer_pdf_path'] as $field) $table->string($field)->nullable();
});
$student = User::factory()->create(['role' => 'student']);
$tutor = User::factory()->create(['role' => 'tutor']);
$grade = Grade::create(['name' => 'Fixture grade']); $subject = Subject::create(['name' => 'Fixture maths']);
LessonAccess::create(['user_id' => $student->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id, 'status' => 'accepted']);
$bank = [];
foreach (['A', 'B', 'C'] as $topic) {
    $lesson = Lesson::create(['grade_id' => $grade->id, 'subject_id' => $subject->id, 'topic' => $topic,
        'title' => "Fixture $topic", 'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
    $path = "lesson-pdfs/$topic.pdf"; Storage::disk('local')->put($path, "%PDF-1.4 $topic");
    $lesson->update(['question_pdf_path' => $path]);
    $source = LessonQuestionSource::where('lesson_id', $lesson->id)->sole();
    $ids = [];
    for ($i = 0; $i < 5; $i++) {
        $id = hash('sha256', "$topic:$i"); $ids[] = $id;
        $bank[$id] = ['id' => $id, 'active' => true, 'grade_id' => $grade->id, 'subject_id' => $subject->id, 'text' => "Question $topic $i",
            'origins' => [['source_key' => "{$grade->id}:{$subject->id}:{$lesson->id}:question", 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'page' => 1, 'label' => (string) $i]]];
        QuestionReview::create(['id' => $id, 'grade_id' => $grade->id, 'subject_id' => $subject->id,
            'status' => 'approved', 'content' => ['text' => "Question $topic $i"], 'origins' => []]);
    }
    $source->update(['status' => 'processed', 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'question_ids' => $ids]);
}
Storage::disk('local')->put('structured/bank.json', json_encode(['questions' => $bank], JSON_THROW_ON_ERROR));
$fixture = ['tutor_id' => $tutor->id, 'input' => ['student_id' => $student->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id,
    'purpose' => 'main', 'batch_key' => (string) Str::uuid(), 'topics' => ['A', 'B', 'C']]];
file_put_contents($root.'/fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
$workers = [];
try {
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'issue'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot launch concurrent verification worker.');
        fclose($pipes[0]);
        $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }
    $deadline = microtime(true) + 20;
    while (count(glob($root.'/ready.*')) !== 2) {
        if (microtime(true) > $deadline) throw new RuntimeException('Workers did not reach the concurrency barrier.');
        usleep(10000);
    }
    file_put_contents($root.'/start', 'go');
    $ids = [];
    foreach ($workers as &$worker) {
        $output = trim(stream_get_contents($worker['stdout'])); $error = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']); fclose($worker['stderr']);
        $code = proc_close($worker['process']); $worker['process'] = null;
        if ($code !== 0 || !Str::isUuid($output)) throw new RuntimeException('Concurrent worker failed: '.$error.' '.$output);
        $ids[] = $output;
    }
    unset($worker);
    if ($ids[0] !== $ids[1] || AssessmentBatch::count() !== 1 || TestPaper::count() !== 1
        || TestPaperAttempt::count() !== 1 || TestPaperQuestion::count() !== 15) throw new RuntimeException('Concurrent assignment created inconsistent records.');
    echo json_encode(['database' => $database, 'concurrent_requests' => 2, 'same_paper' => true,
        'batches' => 1, 'papers' => 1, 'attempts' => 1, 'questions' => 15, 'fixture_directory' => $root], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    foreach ($workers as $worker) if (is_resource($worker['process'])) proc_terminate($worker['process']);
}
// Retain the named isolated database and fixture directory for inspection. No DROP/reset/delete.
