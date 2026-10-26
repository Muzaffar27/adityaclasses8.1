<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\LessonAccess;
use App\Models\Subject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class StudentProfilePermissionsTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    private User $student;
    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase();
        $this->grade = Grade::create(['name' => 'Grade 10']);
        $this->student = User::factory()->create(['id' => random_int(1000000, 2000000), 'role' => 'student']);
        $this->student->studentProfile()->create(['grade_id' => $this->grade->id]);
        Sanctum::actingAs($this->student);
    }

    protected function tearDown(): void
    {
        $this->closeLearningDatabase();
        parent::tearDown();
    }

    private function personalPayload(): array
    {
        return ['name' => 'Updated student', 'email' => $this->student->email];
    }

    public function test_student_cannot_change_clear_or_resubmit_assigned_grade(): void
    {
        $otherGrade = Grade::create(['name' => 'Grade 11']);
        foreach ([$otherGrade->id, null, '', $this->grade->id] as $gradeId) {
            $this->putJson('/api/updateUserInfo', $this->personalPayload() + ['grade_id' => $gradeId])
                ->assertUnprocessable()->assertJsonValidationErrors('grade_id');
            $this->assertSame($this->grade->id, $this->student->fresh()->studentProfile->grade_id);
            $this->assertSame($this->student->name, $this->student->fresh()->name);
        }
    }

    public function test_unassigned_student_cannot_assign_their_own_grade(): void
    {
        $this->student->studentProfile()->update(['grade_id' => null]);
        $this->putJson('/api/updateUserInfo', $this->personalPayload() + ['grade_id' => $this->grade->id])
            ->assertUnprocessable()->assertJsonValidationErrors('grade_id');
        $this->assertNull($this->student->fresh()->studentProfile->grade_id);
    }

    public function test_permitted_updates_preserve_grade_and_saved_guardian_phone(): void
    {
        $this->student->studentProfile()->update(['guardian_phone' => '51234567']);
        $this->putJson('/api/updateUserInfo', $this->personalPayload() + [
            'academic_year' => '2026', 'student_phone' => '59876543',
            'guardian_name' => 'Guardian', 'guardian_relationship' => 'Mother', 'guardian_phone' => '51234567',
        ])->assertOk()->assertJsonPath('user.student_profile.grade.name', 'Grade 10');
        $profile = $this->student->fresh()->studentProfile;
        $this->assertSame($this->grade->id, $profile->grade_id);
        $this->assertSame('2026', $profile->academic_year);
        $this->assertSame('59876543', $profile->student_phone);
        $this->assertSame('Guardian', $profile->guardian_name);
        $this->assertSame('51234567', $profile->guardian_phone);
    }

    public function test_student_can_fill_blank_guardian_phone_but_cannot_replace_or_clear_it(): void
    {
        $this->putJson('/api/updateUserInfo', $this->personalPayload() + ['guardian_phone' => '51234567'])->assertOk();
        foreach (['59876543', null] as $phone) {
            $this->putJson('/api/updateUserInfo', $this->personalPayload() + ['guardian_phone' => $phone])
                ->assertUnprocessable()->assertJsonValidationErrors('guardian_phone');
            $this->assertSame('51234567', $this->student->fresh()->studentProfile->guardian_phone);
        }
    }

    public function test_invalid_personal_input_returns_field_errors_without_saving(): void
    {
        $this->putJson('/api/updateUserInfo', ['name' => '', 'email' => 'invalid', 'student_phone' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'student_phone']);
        $this->assertSame($this->student->name, $this->student->fresh()->name);
    }

    public function test_restricted_account_fields_are_rejected_before_any_personal_changes(): void
    {
        $this->student->studentProfile()->update(['must_change_password' => true]);
        $originalPassword = $this->student->password;
        $restricted = [
            'role' => 'admin', 'subject_id' => 1, 'subject_ids' => [1], 'lesson_access' => [],
            'guardian_report_consent' => true, 'guardian_report_consent_at' => '2026-10-04',
            'must_change_password' => false, 'password' => 'unused-test-password',
        ];
        foreach ($restricted as $field => $value) {
            foreach ([$value, null] as $submittedValue) {
                $this->putJson('/api/updateUserInfo', $this->personalPayload() + [$field => $submittedValue])
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
                $saved = $this->student->fresh();
                $this->assertSame($this->student->name, $saved->name);
                $this->assertSame('student', $saved->role);
                $this->assertSame($originalPassword, $saved->password);
                $this->assertTrue($saved->studentProfile->must_change_password);
                $this->assertNull($saved->studentProfile->guardian_report_consent_at);
                $this->assertSame(0, $saved->lessonAccess()->count());
            }
        }
    }

    public function test_profile_read_and_self_update_cannot_target_another_student(): void
    {
        $other = User::factory()->create(['role' => 'student']);
        $other->studentProfile()->create(['grade_id' => null]);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $this->student->id)
            ->assertJsonPath('user.student_profile.grade_id', $this->grade->id);
        $this->putJson('/api/updateUserInfo', $this->personalPayload() + ['user_id' => $other->id, 'id' => $other->id])
            ->assertOk()->assertJsonPath('user.id', $this->student->id);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertNull($other->fresh()->studentProfile->grade_id);
        $this->putJson('/api/students/' . $other->id . '/profile', $this->personalPayload())
            ->assertForbidden();
        $this->putJson('/api/students/' . $this->student->id . '/profile', $this->personalPayload())
            ->assertForbidden();
        $this->getJson('/api/getStudents')->assertForbidden();
    }

    public function test_tutor_and_admin_can_assign_change_and_clear_student_grade(): void
    {
        $otherGrade = Grade::create(['name' => 'Grade 11']);
        foreach (['tutor', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['id' => random_int(2000001, 3000000), 'role' => $role]));
            foreach ([null, $otherGrade->id, $this->grade->id] as $gradeId) {
                $this->putJson('/api/students/' . $this->student->id . '/profile', $this->personalPayload() + [
                    'grade_id' => $gradeId, 'guardian_report_consent' => false,
                ])->assertOk()->assertJsonPath('student.student_profile.grade_id', $gradeId);
                $this->assertSame($gradeId, $this->student->fresh()->studentProfile->grade_id);
            }
        }
    }

    public function test_unauthenticated_profile_access_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/myCourses')->assertUnauthorized();
        $this->putJson('/api/updateUserInfo', $this->personalPayload())->assertUnauthorized();
        $this->putJson('/api/students/' . $this->student->id . '/profile', $this->personalPayload())->assertUnauthorized();
    }

    public function test_active_subject_source_filters_status_expiry_and_duplicate_pairs(): void
    {
        $this->freezeTime();
        $subject = Subject::create(['name' => 'Mathematics']);
        $base = ['user_id' => $this->student->id, 'grade_id' => $this->grade->id, 'subject_id' => $subject->id];
        $original = LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => null]);
        $latest = LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => now()->addDay()]);
        // Later invalid requests must not hide an earlier valid accepted access.
        foreach (['pending', 'refused'] as $status) {
            LessonAccess::create($base + ['status' => $status, 'expires_at' => null]);
        }
        LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => now()]);
        LessonAccess::create($base + ['status' => 'accepted', 'expires_at' => now()->subSecond()]);
        $secondGrade = Grade::create(['name' => 'Grade 9']);
        $lower = LessonAccess::create(array_merge($base, ['grade_id' => $secondGrade->id, 'status' => 'accepted', 'expires_at' => null]));
        $expiredSubject = Subject::create(['name' => 'Expired subject']);
        LessonAccess::create(array_merge($base, ['subject_id' => $expiredSubject->id, 'status' => 'accepted', 'expires_at' => now()->subDay()]));

        $response = $this->getJson('/api/myCourses')->assertOk()->assertJsonCount(2);
        $rows = collect($response->json());
        $this->assertEqualsCanonicalizing([$latest->id, $lower->id], $rows->pluck('id')->all());
        $this->assertFalse($rows->contains('id', $original->id));
        $this->assertSame(['Mathematics'], $rows->pluck('subject.name')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['Grade 10', 'Grade 9'], $rows->pluck('grade.name')->all());
    }

    public function test_active_subject_source_is_empty_without_access_and_cannot_target_other_students(): void
    {
        $other = User::factory()->create(['role' => 'student']);
        $subject = Subject::create(['name' => 'Private subject']);
        LessonAccess::create([
            'user_id' => $other->id, 'grade_id' => $this->grade->id, 'subject_id' => $subject->id,
            'status' => 'accepted', 'expires_at' => null,
        ]);
        $this->getJson('/api/myCourses?user_id=' . $other->id)->assertOk()->assertExactJson([]);
        Sanctum::actingAs($other);
        $this->getJson('/api/myCourses')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.user_id', $other->id)->assertJsonPath('0.subject.name', 'Private subject');
    }
}
