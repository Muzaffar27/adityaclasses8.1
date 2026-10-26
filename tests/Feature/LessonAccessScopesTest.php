<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\LessonAccess;
use App\Models\Subject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class LessonAccessScopesTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase(true);
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        $this->closeLearningDatabase();
        parent::tearDown();
    }

    public function test_latest_active_access_matches_the_previous_query_and_both_course_endpoints(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $grade = Grade::create(['name' => 'Grade 10']);
        $lowerGrade = Grade::create(['name' => 'Grade 9']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $base = ['user_id' => $student->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id];
        LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => null]);
        $latest = LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => now()->addDay()]);
        foreach (['pending', 'refused'] as $status) {
            LessonAccess::create($base + ['status' => $status, 'expires_at' => null]);
        }
        foreach ([now(), now()->subSecond()] as $expiry) {
            LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => $expiry]);
        }
        $lower = LessonAccess::create(array_replace($base, [
            'grade_id' => $lowerGrade->id, 'status' => 'accepted', 'expires_at' => null,
        ]));
        LessonAccess::create(array_replace($base, [
            'user_id' => $other->id, 'status' => 'accepted', 'expires_at' => null,
        ]));

        $previous = LessonAccess::where('user_id', $student->id)->where('status', 'accepted')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('id', function ($query) use ($student) {
                $query->selectRaw('MAX(id)')->from('lesson_access')->where('user_id', $student->id)
                    ->where('status', 'accepted')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->groupBy('subject_id', 'grade_id');
            })->orderBy('id')->pluck('id')->all();
        $this->assertSame([$latest->id, $lower->id], $previous);
        $this->assertSame($previous, LessonAccess::latestActiveForUser($student->id)->orderBy('id')->pluck('id')->all());

        Sanctum::actingAs($student);
        $courses = $this->getJson('/api/myCourses')->assertOk()->json();
        $dashboard = $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('stats.active_courses', 2)->json('courses');
        $this->assertEqualsCanonicalizing($previous, array_column($courses, 'id'));
        $this->assertEqualsCanonicalizing($previous, array_column($dashboard, 'id'));
    }

    public function test_active_scope_keeps_existing_user_and_course_restrictions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $grade = Grade::create(['name' => 'Grade 10']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $base = ['user_id' => $student->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id];
        $eligible = LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => null]);
        LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => now()]);
        LessonAccess::create(array_replace($base, ['user_id' => $other->id, 'status' => 'accepted']));
        $this->assertSame([$eligible->id], LessonAccess::where($base)->active()->pluck('id')->all());
        $this->assertSame([], LessonAccess::where($base)->where('status', 'pending')->active()->pluck('id')->all());
    }
}
