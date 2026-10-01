<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Lesson $lesson;
    private LessonAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create(['role' => 'student']);
        $this->lesson = Lesson::create([
            'grade_id' => Grade::create(['name' => 'Grade 10'])->id,
            'subject_id' => Subject::create(['name' => 'Mathematics'])->id,
            'topic' => 'Algebra', 'title' => 'Quadratics', 'is_active' => true,
            'vimeo_url' => 'https://player.vimeo.com/video/1',
            'answer_vimeo_url' => 'https://player.vimeo.com/video/2',
        ]);
        $this->access = LessonAccess::create([
            'user_id' => $this->student->id, 'grade_id' => $this->lesson->grade_id,
            'subject_id' => $this->lesson->subject_id, 'status' => 'accepted',
            'expires_at' => now()->addMonth(),
        ]);
        Sanctum::actingAs($this->student);
    }

    private function saveProgress(float $position, ?float $duration = 100, string $type = 'lesson')
    {
        return $this->putJson('/api/lesson-progress/' . $this->lesson->id, [
            'video_type' => $type, 'position_seconds' => $position, 'duration_seconds' => $duration,
        ]);
    }

    private function lessonList()
    {
        return $this->getJson('/api/lessons?subject_id=' . $this->lesson->subject_id
            . '&grade_id=' . $this->lesson->grade_id);
    }

    public function test_completion_threshold_and_replay_preserve_the_original_completion(): void
    {
        $this->lessonList()->assertOk()->assertJsonPath('lessons.0.progress.status', 'not_started');
        $this->saveProgress(94.9)->assertOk()->assertJsonPath('completed', false)
            ->assertJsonPath('progress.status', 'in_progress')->assertJsonPath('progress.percent', 94);
        $this->travel(30)->seconds();
        $this->saveProgress(95)->assertOk()->assertJsonPath('completed', true)
            ->assertJsonPath('progress.percent', 100);
        $completedAt = LessonProgress::first()->completed_at->toISOString();
        $this->travel(1)->hour();
        $this->saveProgress(10)->assertOk()->assertJsonPath('completed', true);
        $this->putJson('/api/lesson-progress/' . $this->lesson->id, [
            'video_type' => 'lesson', 'viewed_only' => true,
        ])->assertOk()->assertJsonPath('completed', true);
        $this->saveProgress(100)->assertOk();
        $this->assertSame($completedAt, LessonProgress::first()->completed_at->toISOString());
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'completed');
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('continue_learning', null);
    }

    public function test_answer_progress_is_separate_and_does_not_complete_the_lesson(): void
    {
        $this->saveProgress(94, 100, 'answer')->assertOk();
        $this->travel(30)->seconds();
        $this->saveProgress(100, 100, 'answer')->assertOk()
            ->assertJsonPath('progress.answer_video.status', 'completed')
            ->assertJsonPath('progress.status', 'not_started');
        $this->saveProgress(40)->assertOk()->assertJsonPath('progress.percent', 40)
            ->assertJsonPath('progress.answer_video.status', 'completed');
        $this->getJson('/api/lesson-progress/' . $this->lesson->id . '?video_type=answer')
            ->assertJsonPath('completed', true);
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'in_progress');
    }

    public function test_invalid_playback_values_cannot_complete_a_video(): void
    {
        $this->saveProgress(-1)->assertUnprocessable();
        $this->saveProgress(101)->assertUnprocessable();
        $this->saveProgress(1, 0)->assertUnprocessable();
        $this->saveProgress(1, -10)->assertUnprocessable();
        $this->saveProgress(90000, 90000)->assertUnprocessable();
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->saveProgress(95, null)->assertOk()->assertJsonPath('completed', false);
        $this->saveProgress(20)->assertOk();
        $this->saveProgress(101, null)->assertUnprocessable();
        $this->lesson->update(['answer_vimeo_url' => null]);
        $this->saveProgress(100, 100, 'answer')->assertUnprocessable();
    }

    public function test_completion_requires_consistent_duration_and_elapsed_viewing_time(): void
    {
        $this->saveProgress(95, 100)->assertUnprocessable()
            ->assertJsonValidationErrors('position_seconds');
        $this->saveProgress(40, 100)->assertOk();
        $this->saveProgress(45, 130)->assertUnprocessable()
            ->assertJsonValidationErrors('duration_seconds');
        $this->travel(30)->seconds();
        $this->saveProgress(95, 100)->assertOk()->assertJsonPath('completed', true);
    }

    public function test_replacing_a_video_resets_only_that_video_progress(): void
    {
        $this->saveProgress(40)->assertOk();
        $this->saveProgress(20, 100, 'answer')->assertOk();
        $tutor = User::factory()->create(['role' => 'tutor']);
        Sanctum::actingAs($tutor);
        $this->putJson('/api/admin/lessons/' . $this->lesson->id, [
            'vimeo_url' => 'https://player.vimeo.com/video/999',
        ])->assertOk();

        Sanctum::actingAs($this->student);
        $this->lesson->refresh();
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'not_started')
            ->assertJsonPath('lessons.0.progress.answer_video.status', 'in_progress');
        $this->getJson('/api/lesson-progress/' . $this->lesson->id)
            ->assertJsonPath('position_seconds', 0)
            ->assertJsonPath('completed', false);
        $this->getJson('/api/lesson-progress/' . $this->lesson->id . '?video_type=answer')
            ->assertJsonPath('position_seconds', 20);
    }

    public function test_pdf_opens_are_viewed_without_completing_video_or_entering_continue_watching(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson.pdf', "%PDF-1.4\n%%EOF");
        $this->lesson->update(['lesson_pdf_path' => 'lesson.pdf', 'answer_pdf_path' => 'lesson.pdf']);
        $this->get('/api/lessons/' . $this->lesson->id . '/pdf/answer')->assertOk();
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'not_started')
            ->assertJsonPath('lessons.0.progress.pdfs.answer', 'viewed');
        $this->get('/api/lessons/' . $this->lesson->id . '/pdf/lesson')->assertOk();
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'not_started')
            ->assertJsonPath('lessons.0.progress.pdfs.lesson', 'viewed');
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('continue_learning', null);
        $this->lesson->update(['vimeo_url' => null]);
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'viewed')
            ->assertJsonPath('lessons.0.progress.percent', null);
        $this->assertSame(0, LessonProgress::whereNotNull('completed_at')->count());
        $this->saveProgress(100)->assertUnprocessable();
        $this->get('/api/lessons/' . $this->lesson->id . '/pdf/question')->assertNotFound();
        $this->assertDatabaseMissing('lesson_progress', ['video_type' => 'pdf_question']);
    }

    public function test_expired_access_and_other_students_cannot_read_or_modify_progress(): void
    {
        $this->saveProgress(50)->assertOk();
        $otherStudent = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($otherStudent);
        $this->getJson('/api/lesson-progress/' . $this->lesson->id)->assertForbidden();
        $this->saveProgress(100)->assertForbidden();
        $this->lessonList()->assertJsonMissingPath('lessons.0.progress');
        LessonAccess::create([
            'user_id' => $otherStudent->id, 'grade_id' => $this->lesson->grade_id,
            'subject_id' => $this->lesson->subject_id, 'status' => 'accepted',
        ]);
        $this->lessonList()->assertJsonPath('lessons.0.progress.status', 'not_started');
        Sanctum::actingAs($this->student);
        $this->access->update(['expires_at' => now()->subDay()]);
        $this->saveProgress(100)->assertForbidden();
        $this->lessonList()->assertJsonMissingPath('lessons.0.progress');
    }
}
