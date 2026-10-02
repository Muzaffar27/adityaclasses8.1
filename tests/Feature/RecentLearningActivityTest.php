<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use App\Models\Subject;
use App\Models\User;
use App\Services\LessonProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecentLearningActivityTest extends TestCase
{
    private User $student;
    private Lesson $lesson;
    private LessonAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        // A new private in-memory connection: never migrate or reset the application database.
        config(['database.default' => 'activity_testing', 'database.connections.activity_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_03_24_163241_create_grades_table.php',
            '2026_03_24_163313_create_subjects_table.php',
            '2026_03_24_163323_create_lessons_table.php',
            '2026_03_26_185603_create_lesson_access_table.php',
            '2026_06_04_000001_add_duration_price_and_expiry_to_lesson_access_table.php',
            '2026_09_21_000001_create_student_profiles_table.php',
            '2026_09_26_000002_create_lesson_progress_table.php',
            '2026_10_01_000001_add_video_source_to_lesson_progress_table.php',
        ] as $migration) {
            if ($migration === '2026_09_21_000001_create_student_profiles_table.php') {
                Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('student'));
            }
            (require database_path('migrations/' . $migration))->up();
        }
        Schema::table('lessons', function (Blueprint $table) {
            foreach (['answer_vimeo_url', 'lesson_pdf_path', 'question_pdf_path', 'question_pdf_2_path', 'answer_pdf_path'] as $field) {
                $table->string($field)->nullable();
            }
        });
        DB::beginTransaction();
        Storage::fake('local');
        $this->student = User::factory()->create(['role' => 'student']);
        $this->lesson = Lesson::create([
            'grade_id' => Grade::create(['name' => 'Grade 10'])->id,
            'subject_id' => Subject::create(['name' => 'Mathematics'])->id,
            'topic' => 'Algebra', 'title' => 'Quadratics', 'is_active' => true,
            'vimeo_url' => 'https://player.vimeo.com/video/1', 'answer_vimeo_url' => 'https://player.vimeo.com/video/2',
        ]);
        $this->access = LessonAccess::create([
            'user_id' => $this->student->id, 'grade_id' => $this->lesson->grade_id,
            'subject_id' => $this->lesson->subject_id, 'status' => 'accepted', 'expires_at' => now()->addMonth(),
        ]);
        Sanctum::actingAs($this->student);
    }

    protected function tearDown(): void
    {
        DB::connection('activity_testing')->rollBack();
        DB::purge('activity_testing');
        parent::tearDown();
    }

    private function record(string $type, int $minutesAgo = 0, ?Lesson $lesson = null): LessonProgress
    {
        $lesson ??= $this->lesson;
        $field = ['pdf_lesson' => 'lesson_pdf_path', 'pdf_question' => 'question_pdf_path',
            'pdf_question2' => 'question_pdf_2_path', 'pdf_answer' => 'answer_pdf_path'][$type] ?? null;
        if ($field) {
            $path = $lesson->id . '/' . $type . '.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4');
            $lesson->update([$field => $path]);
        }
        return LessonProgress::create([
            'user_id' => $this->student->id, 'lesson_id' => $lesson->id, 'video_type' => $type,
            'video_source' => $field ? null : app(LessonProgressService::class)->videoSource($lesson->{$type === 'answer' ? 'answer_vimeo_url' : 'vimeo_url'}),
            'last_viewed_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_empty_activity_and_no_access(): void
    {
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('recent_activity', []);
        $this->record('lesson');
        $this->access->update(['status' => 'pending']);
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('recent_activity', []);
    }

    public function test_latest_five_resources_are_separate_and_completed_videos_are_included(): void
    {
        $this->freezeTime();
        foreach (['lesson', 'answer', 'pdf_lesson', 'pdf_question', 'pdf_question2', 'pdf_answer'] as $i => $type) {
            $row = $this->record($type, 6 - $i);
            if ($type === 'answer') $row->update(['completed_at' => now()]);
        }
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonCount(5, 'recent_activity')
            ->assertJsonPath('recent_activity.0.resource_type', 'pdf_answer')
            ->assertJsonPath('recent_activity.4.resource_type', 'answer')
            ->assertJsonPath('recent_activity.4.lesson_id', $this->lesson->id);
    }

    public function test_ties_use_record_id_and_repeat_open_moves_resource_without_duplicate(): void
    {
        $this->freezeTime();
        $video = $this->record('lesson', 2);
        $this->record('answer', 1);
        $pdf = $this->record('pdf_lesson', 1);
        $this->getJson('/api/student/dashboard')->assertJsonPath('recent_activity.0.id', $pdf->id);
        $this->putJson('/api/lesson-progress/' . $this->lesson->id, ['video_type' => 'lesson', 'viewed_only' => true])->assertOk();
        $this->getJson('/api/student/dashboard')->assertJsonCount(3, 'recent_activity')->assertJsonPath('recent_activity.0.id', $video->id);
    }

    public function test_removed_resources_and_replaced_or_legacy_videos_are_filtered_before_limit(): void
    {
        $valid = $this->record('pdf_lesson', 10);
        $this->record('lesson')->update(['video_source' => 'old-source']);
        $this->record('answer')->update(['video_source' => null]);
        $this->record('pdf_question');
        $this->lesson->update(['question_pdf_path' => null]);
        $this->record('pdf_question2');
        Storage::disk('local')->delete($this->lesson->question_pdf_2_path);
        $this->record('pdf_answer');
        $this->lesson->update(['answer_pdf_path' => null]);
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonCount(1, 'recent_activity')->assertJsonPath('recent_activity.0.id', $valid->id);
    }

    public function test_other_students_expired_access_inactive_and_deleted_lessons_are_excluded(): void
    {
        $this->record('lesson');
        $other = User::factory()->create(['role' => 'student']);
        LessonAccess::create([
            'user_id' => $other->id, 'grade_id' => $this->lesson->grade_id,
            'subject_id' => $this->lesson->subject_id, 'status' => 'accepted', 'expires_at' => null,
        ]);
        Sanctum::actingAs($other);
        $this->getJson('/api/student/dashboard')->assertJsonPath('recent_activity', []);
        Sanctum::actingAs($this->student);
        $this->access->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/api/student/dashboard')->assertJsonPath('recent_activity', []);
        $this->access->update(['expires_at' => null]);
        $this->lesson->update(['is_active' => false]);
        $this->getJson('/api/student/dashboard')->assertJsonPath('recent_activity', []);
        $this->lesson->delete();
        $this->getJson('/api/student/dashboard')->assertJsonPath('recent_activity', []);
    }
}
