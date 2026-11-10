<?php

namespace Tests\Feature;

use App\Models\{Grade, Subject, Lesson, LessonAccess, LessonProgress, User};
use App\Services\LessonProgressService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class ManualLessonCompletionTest extends TestCase
{
    use UsesIsolatedLearningDatabase;
    private User $student;
    private Lesson $lesson;
    private LessonAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase(true);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->lesson = Lesson::create(['grade_id' => Grade::create(['name' => 'Grade 8'])->id,
            'subject_id' => Subject::create(['name' => 'Maths'])->id, 'title' => 'Fractions', 'topic' => 'Numbers',
            'is_active' => true, 'vimeo_url' => 'https://player.vimeo.com/video/123']);
        $this->access = LessonAccess::create(['user_id' => $this->student->id, 'grade_id' => $this->lesson->grade_id,
            'subject_id' => $this->lesson->subject_id, 'status' => 'accepted', 'expires_at' => now()->addMonth()]);
        Sanctum::actingAs($this->student);
    }
    protected function tearDown(): void { $this->closeLearningDatabase(); parent::tearDown(); }
    private function complete(array $payload = [])
    {
        return $this->putJson('/api/lesson-progress/'.$this->lesson->id.'/completion', $payload + ['video_url' => $this->lesson->vimeo_url]);
    }
    public function test_explicit_completion_is_idempotent_without_fabricating_playback_or_activity(): void
    {
        $this->complete()->assertOk()->assertJsonPath('progress.status', 'completed');
        $record = LessonProgress::sole();
        $this->assertSame(0, $record->position_seconds);
        $this->assertNull($record->duration_seconds);
        $this->assertDatabaseCount('student_learning_days', 0);
        $time = $record->completed_at->toISOString();
        $this->travel(1)->hour();
        $this->complete()->assertOk();
        $this->assertSame($time, $record->fresh()->completed_at->toISOString());
        $this->assertDatabaseCount('lesson_progress', 1);
    }
    public function test_completion_preserves_position_and_does_not_complete_the_answer_video(): void
    {
        LessonProgress::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'video_type' => 'lesson',
            'video_source' => app(LessonProgressService::class)->videoSource($this->lesson->vimeo_url), 'position_seconds' => 42, 'duration_seconds' => 100]);
        $this->complete()->assertOk()->assertJsonPath('progress.answer_video.status', 'not_started');
        $this->assertSame(42, LessonProgress::sole()->position_seconds);
        $this->assertSame(100, LessonProgress::sole()->duration_seconds);
    }
    public function test_access_source_and_allowed_roles_are_enforced(): void
    {
        $this->complete(['video_url' => 'https://player.vimeo.com/video/999'])->assertStatus(409);
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->access->update(['expires_at' => now()->subDay()]);
        $this->complete()->assertForbidden();
        $other = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($other);
        $this->complete()->assertForbidden();
        $this->access->update(['expires_at' => now()->addMonth()]);
        $this->student->update(['role' => 'guest']);
        Sanctum::actingAs($this->student);
        $this->complete()->assertForbidden();
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_tutors_and_admins_complete_only_their_own_video_even_with_a_student_id_in_the_payload(): void
    {
        $studentProgress = LessonProgress::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id,
            'video_type' => 'lesson', 'video_source' => app(LessonProgressService::class)->videoSource($this->lesson->vimeo_url),
            'position_seconds' => 42, 'duration_seconds' => 100]);
        $before = $studentProgress->fresh()->toArray();
        foreach (['tutor', 'admin'] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            LessonAccess::create(['user_id' => $staff->id, 'grade_id' => $this->lesson->grade_id,
                'subject_id' => $this->lesson->subject_id, 'status' => 'accepted', 'expires_at' => now()->addMonth()]);
            Sanctum::actingAs($staff);
            $this->complete(['user_id' => $this->student->id])->assertOk()->assertJsonPath('progress.lesson_video.status', 'completed');
            $this->assertNotNull(LessonProgress::where('user_id', $staff->id)->sole()->completed_at);
            $this->assertSame($before, $studentProgress->fresh()->toArray());
        }
        $this->assertDatabaseCount('lesson_progress', 3);
        $this->assertDatabaseCount('student_learning_days', 0);
    }

    public function test_each_video_completes_independently_and_checks_its_own_source(): void
    {
        $this->lesson->update(['answer_vimeo_url' => 'https://player.vimeo.com/video/456']);
        $this->complete(['video_type' => 'answer', 'video_url' => $this->lesson->vimeo_url])->assertStatus(409);
        $this->complete(['video_type' => 'answer', 'video_url' => $this->lesson->answer_vimeo_url])->assertOk()
            ->assertJsonPath('progress.answer_video.status', 'completed')->assertJsonPath('progress.lesson_video.status', 'not_started');
        $this->complete()->assertOk()->assertJsonPath('progress.lesson_video.status', 'completed')
            ->assertJsonPath('progress.answer_video.status', 'completed');
        $this->assertDatabaseCount('lesson_progress', 2);
        $this->assertDatabaseCount('student_learning_days', 0);
    }
}
