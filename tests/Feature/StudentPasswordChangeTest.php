<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class StudentPasswordChangeTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    private User $student;
    private string $oldPassword = 'Original-test-password1!';
    private string $newPassword = 'Replacement-test-password2!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase(true);
        $this->student = User::factory()->create([
            'id' => random_int(3000001, 4000000), 'role' => 'student', 'password' => $this->oldPassword,
        ]);
        $this->student->studentProfile()->create(['must_change_password' => true]);
        Sanctum::actingAs($this->student);
    }

    protected function tearDown(): void
    {
        $this->closeLearningDatabase();
        parent::tearDown();
    }

    private function payload(): array
    {
        return [
            'current_password' => $this->oldPassword, 'password' => $this->newPassword,
            'password_confirmation' => $this->newPassword,
        ];
    }

    private function assertPasswordUnchanged(): void
    {
        $saved = $this->student->fresh();
        $this->assertTrue(Hash::check($this->oldPassword, $saved->password));
        $this->assertTrue($saved->studentProfile->must_change_password);
    }

    public function test_wrong_current_password_cannot_change_password(): void
    {
        $this->putJson('/api/updateUserPwd', array_replace($this->payload(), ['current_password' => 'Incorrect-test-password']))
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertPasswordUnchanged();
    }

    public function test_invalid_password_inputs_return_field_errors_without_saving(): void
    {
        foreach ([
            ['current_password', null], ['current_password', ['invalid']], ['current_password', 12345678],
            ['password', null], ['password', 'short'], ['password', 12345678], ['password', array_fill(0, 8, 'invalid')],
            ['password_confirmation', 'Different-test-password'],
        ] as [$field, $value]) {
            $payload = array_replace($this->payload(), [$field => $value]);
            if ($field === 'password') $payload['password_confirmation'] = $value;
            $this->putJson('/api/updateUserPwd', $payload)->assertUnprocessable()
                ->assertJsonValidationErrors($field === 'password_confirmation' ? 'password' : $field);
            $this->assertPasswordUnchanged();
        }
    }

    public function test_successful_change_hashes_password_and_clears_forced_change(): void
    {
        $response = $this->putJson('/api/updateUserPwd', $this->payload())->assertOk()
            ->assertJsonPath('user.id', $this->student->id)
            ->assertJsonPath('user.student_profile.must_change_password', false);
        $saved = $this->student->fresh();
        $this->assertNotSame($this->newPassword, $saved->password);
        $this->assertTrue(Hash::check($this->newPassword, $saved->password));
        $this->assertFalse(Hash::check($this->oldPassword, $saved->password));
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('user'));
        $this->assertStringNotContainsString($this->newPassword, $response->getContent());
        $this->assertStringNotContainsString($saved->password, $response->getContent());
    }

    public function test_login_accepts_new_password_and_rejects_old_password_after_change(): void
    {
        $this->putJson('/api/updateUserPwd', $this->payload())->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $this->student->email, 'password' => $this->oldPassword])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $response = $this->postJson('/api/login', ['email' => $this->student->email, 'password' => $this->newPassword])
            ->assertOk()->assertJsonPath('user.id', $this->student->id)->assertJsonStructure(['token']);
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertStringNotContainsString($this->newPassword, $response->getContent());
    }

    public function test_supplied_student_ids_cannot_change_another_accounts_password(): void
    {
        $other = User::factory()->create(['role' => 'student', 'password' => $this->oldPassword]);
        $originalHash = $other->password;
        $this->putJson('/api/updateUserPwd', $this->payload() + ['id' => $other->id, 'user_id' => $other->id])
            ->assertOk()->assertJsonPath('user.id', $this->student->id);
        $this->assertSame($originalHash, $other->fresh()->password);
        $this->assertTrue(Hash::check($this->newPassword, $this->student->fresh()->password));
    }

    public function test_unauthenticated_password_change_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/updateUserPwd', $this->payload())->assertUnauthorized();
        $this->assertPasswordUnchanged();
    }
}
