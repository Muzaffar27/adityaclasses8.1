<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\LessonAccess;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_does_not_downgrade_existing_accepted_access(): void
    {
        [$user, $subject, $grade] = $this->accessFixture();

        LessonAccess::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/lesson-access/request', [
            [
                'subject_id' => $subject->id,
                'grade_id' => $grade->id,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('lesson_access', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'status' => 'accepted',
        ]);

        $this->assertSame(1, LessonAccess::count());
    }

    public function test_lesson_access_check_prefers_accepted_duplicate_records(): void
    {
        [$user, $subject, $grade] = $this->accessFixture();

        LessonAccess::insert([
            [
                'user_id' => $user->id,
                'subject_id' => $subject->id,
                'grade_id' => $grade->id,
                'status' => 'pending',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'user_id' => $user->id,
                'subject_id' => $subject->id,
                'grade_id' => $grade->id,
                'status' => 'accepted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/lessons?subject_id={$subject->id}&grade_id={$grade->id}")
            ->assertOk()
            ->assertJsonPath('access.has_access', true)
            ->assertJsonPath('access.status', 'accepted');
    }

    private function accessFixture(): array
    {
        $user = User::factory()->create(['role' => 'student']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $grade = Grade::create(['name' => 'Grade 11']);

        return [$user, $subject, $grade];
    }
}
