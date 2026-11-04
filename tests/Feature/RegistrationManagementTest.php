<?php

namespace Tests\Feature;

use App\Models\CourseRegistration;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegistrationAcknowledgement;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationManagementTest extends TestCase
{
    private User $tutor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['registration.mail_enabled' => true, 'cache.default' => 'array', 'session.driver' => 'array', 'database.default' => 'registration_staff_testing',
            'database.connections.registration_staff_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        $apiPolicy = app(\Illuminate\Cache\RateLimiter::class)->limiter('api');
        $limiter = new \Illuminate\Cache\RateLimiter(app('cache')->store('array'));
        $limiter->for('api', $apiPolicy);
        $this->app->instance(\Illuminate\Cache\RateLimiter::class, $limiter);
        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('student'));
        (require database_path('migrations/2026_11_04_000001_create_course_registrations_table.php'))->up();
        (require database_path('migrations/2026_11_04_000002_add_staff_workflow_to_course_registrations.php'))->up();
        $this->tutor = User::factory()->create(['role' => 'tutor']);
    }

    protected function tearDown(): void
    {
        DB::purge('registration_staff_testing');
        parent::tearDown();
    }

    private function registration(array $details = [], array $attributes = []): CourseRegistration
    {
        $registration = CourseRegistration::create([
            'submission_id' => (string) Str::uuid(), 'reference' => 'AC-2027-'.Str::ulid(), 'payload_hash' => str_repeat('a', 64),
            'details' => array_replace(['studentName' => 'Pending Student', 'grade' => 'Grade 8', 'school' => 'Test School',
                'returning' => 'New student', 'guardian' => 'Test Guardian', 'phone' => '+230 50000000', 'email' => 'guardian@example.test',
                'subjects' => ['English'], 'timetable' => [['subject' => 'English', 'batch' => null, 'note' => null,
                    'day' => 'Saturday', 'time' => '1:15–2:15 pm', 'start' => '9 January 2027']],
                'monthly_total' => 500, 'monthly_fee_per_subject' => 500, 'consent' => true, 'updates' => false, 'message' => 'Parent question'], $details),
        ]);
        if ($attributes) $registration->forceFill($attributes)->save();
        return $registration->fresh();
    }

    private function changes(CourseRegistration $registration, array $changes = []): array
    {
        return array_replace(['registration_status' => 'contacted', 'payment_status' => 'unverified',
            'staff_notes' => 'Called parent; waiting for reply.', 'version' => $registration->workflow_version], $changes);
    }

    public function test_guests_and_students_cannot_list_read_update_or_export_registrations(): void
    {
        $registration = $this->registration();
        foreach (['/api/admin/registrations', '/api/admin/registrations/'.$registration->id, '/api/admin/registrations/export'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration))->assertUnauthorized();
        $this->postJson('/api/admin/registrations/'.$registration->id.'/resend-acknowledgement')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        foreach (['/api/admin/registrations', '/api/admin/registrations/'.$registration->id, '/api/admin/registrations/export'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration))->assertForbidden();
        $this->postJson('/api/admin/registrations/'.$registration->id.'/resend-acknowledgement')->assertForbidden();
        $this->getJson('/api/admin/registrations/999999')->assertForbidden();
        $this->assertSame('new', $registration->fresh()->registration_status);
    }

    public function test_pending_defaults_counts_and_full_details_are_available_to_staff_only(): void
    {
        $registration = $this->registration();
        $this->registration(['studentName' => 'Contacted Student'], ['registration_status' => 'contacted']);
        $this->registration(['studentName' => 'Enrolled Student'], ['registration_status' => 'enrolled']);
        Sanctum::actingAs($this->tutor);
        $response = $this->getJson('/api/admin/registrations')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('counts.pending', 2)->assertJsonPath('counts.enrolled', 1);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('payload_hash', $response->json('data.0'));
        $this->assertArrayNotHasKey('details', $response->json('data.0'));
        $this->getJson('/api/admin/registrations/'.$registration->id)->assertOk()
            ->assertJsonPath('details.school', 'Test School')->assertJsonPath('details.message', 'Parent question')
            ->assertJsonPath('details.timetable.0.start', '9 January 2027')->assertJsonPath('details.consent', true)
            ->assertJsonPath('email_status', 'pending')->assertJsonMissingPath('submission_id')->assertJsonMissingPath('payload_hash');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/registrations/'.$registration->id)->assertOk();
    }

    public function test_filters_search_and_pagination_use_saved_registration_information(): void
    {
        Sanctum::actingAs($this->tutor);
        $target = $this->registration(['studentName' => 'Unique Learner', 'grade' => 'Grade 9', 'subjects' => ['Science']], ['payment_status' => 'verified', 'acknowledged_at' => now()]);
        for ($i = 0; $i < 16; $i++) $this->registration(['studentName' => 'Other Student '.$i]);
        $this->getJson('/api/admin/registrations')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/admin/registrations?page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/registrations?'.http_build_query(['search' => 'Unique', 'grade' => 'Grade 9', 'subject' => 'Science', 'payment' => 'verified']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/api/admin/registrations?search=not-present')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/registrations?email=sent')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/api/admin/registrations?email=pending')->assertOk()->assertJsonPath('meta.total', 16);
        $this->getJson('/api/admin/registrations?email=invalid')->assertUnprocessable();
        $this->getJson('/api/admin/registrations?status=enrolled')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/registrations?status=invalid')->assertUnprocessable();
    }

    public function test_staff_can_save_notes_and_status_without_creating_an_account_or_claiming_payment(): void
    {
        Sanctum::actingAs($this->tutor);
        $registration = $this->registration();
        $beforeUsers = User::count();
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration, ['registration_status' => 'enrolled']))
            ->assertOk()->assertJsonPath('registration_status', 'enrolled')->assertJsonPath('payment_status', 'unverified')
            ->assertJsonPath('version', 1)->assertJsonPath('staff_updated_by', $this->tutor->name);
        $this->assertSame($beforeUsers, User::count());
        $this->assertSame('Called parent; waiting for reply.', $registration->fresh()->staff_notes);
        $this->assertSame($registration->details, $registration->fresh()->details);
        $this->assertNull($registration->fresh()->payment_verified_at);
    }

    public function test_payment_verification_requires_explicit_confirmation_and_records_the_operator(): void
    {
        Sanctum::actingAs($this->tutor);
        $registration = $this->registration();
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration, ['payment_status' => 'verified']))
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_payment');
        $this->assertSame('unverified', $registration->fresh()->payment_status);
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration, ['payment_status' => 'verified', 'confirm_payment' => true]))
            ->assertOk()->assertJsonPath('payment_verified_by', $this->tutor->name);
        $verified = $registration->fresh();
        $this->assertNotNull($verified->payment_verified_at);
        $this->assertSame($this->tutor->id, $verified->payment_verified_by);
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($verified))
            ->assertOk()->assertJsonPath('payment_status', 'unverified')->assertJsonPath('payment_verified_at', null);
    }

    public function test_stale_saves_cannot_overwrite_another_tutors_changes(): void
    {
        Sanctum::actingAs($this->tutor);
        $registration = $this->registration();
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration))->assertOk();
        $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration, ['staff_notes' => 'Stale overwrite']))
            ->assertConflict();
        $this->assertSame('Called parent; waiting for reply.', $registration->fresh()->staff_notes);
        $this->assertSame(1, $registration->fresh()->workflow_version);
    }

    public function test_invalid_updates_and_missing_records_leave_saved_details_unchanged(): void
    {
        Sanctum::actingAs($this->tutor);
        $registration = $this->registration();
        foreach ([['registration_status' => 'approved'], ['payment_status' => 'paid'], ['staff_notes' => str_repeat('x', 5001)], ['version' => -1]] as $changes) {
            $this->putJson('/api/admin/registrations/'.$registration->id, $this->changes($registration, $changes))->assertUnprocessable();
        }
        $this->getJson('/api/admin/registrations/999999')->assertNotFound();
        $this->assertSame('new', $registration->fresh()->registration_status);
        $this->assertNull($registration->fresh()->staff_notes);
    }

    public function test_csv_export_obeys_filters_and_neutralizes_spreadsheet_formulas(): void
    {
        Sanctum::actingAs($this->tutor);
        $this->registration(['studentName' => '=HYPERLINK("https://example.test")', 'grade' => 'Grade 9'], ['staff_notes' => ' +SUM(1,2)']);
        $this->registration(['studentName' => 'Excluded student', 'grade' => 'Grade 7']);
        $response = $this->get('/api/admin/registrations/export?'.http_build_query(['grade' => 'Grade 9']), ['Accept' => 'text/csv'])->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("' +SUM", $csv);
        $this->assertStringNotContainsString('Excluded student', $csv);
        $this->assertStringContainsString('9 January 2027', $csv);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_large_inbox_has_bounded_page_sizes_stable_sorting_and_complete_sorted_export(): void
    {
        Sanctum::actingAs($this->tutor);
        for ($i = 250; $i >= 1; $i--) {
            $this->registration(['studentName' => 'Scale Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                ['created_at' => now()->startOfDay()->addSeconds($i)]);
        }
        $this->getJson('/api/admin/registrations?per_page=25&sort=newest')->assertOk()
            ->assertJsonCount(25, 'data')->assertJsonPath('meta.last_page', 10)
            ->assertJsonPath('meta.from', 1)->assertJsonPath('meta.to', 25)->assertJsonPath('data.0.student', 'Scale Student 250');
        $this->getJson('/api/admin/registrations?per_page=50&sort=oldest')->assertOk()
            ->assertJsonCount(50, 'data')->assertJsonPath('data.0.student', 'Scale Student 001');
        $this->getJson('/api/admin/registrations?per_page=100&sort=student&page=3')->assertOk()
            ->assertJsonCount(50, 'data')->assertJsonPath('meta.from', 201)->assertJsonPath('meta.to', 250)
            ->assertJsonPath('data.0.student', 'Scale Student 201')->assertJsonPath('data.49.student', 'Scale Student 250');
        $this->getJson('/api/admin/registrations?per_page=1000')->assertUnprocessable();
        $this->getJson('/api/admin/registrations?sort=invalid')->assertUnprocessable();
        $this->getJson('/api/admin/registrations?search=Scale%20Student%20250')->assertOk()->assertJsonPath('meta.total', 1);
        $response = $this->get('/api/admin/registrations/export?sort=student')->assertOk();
        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertCount(251, $rows);
        $this->assertSame('Scale Student 001', $rows[1][2]);
        $this->assertSame('Scale Student 250', $rows[250][2]);
        $this->assertCount(250, array_unique(array_column(array_slice($rows, 1), 2)));
    }

    public function test_staff_can_retry_pending_acknowledgement_without_duplicate_sends_or_changed_notes(): void
    {
        Sanctum::actingAs($this->tutor);
        Mail::fake();
        $registration = $this->registration([], ['staff_notes' => 'Keep tutor notes']);
        $url = '/api/admin/registrations/'.$registration->id.'/resend-acknowledgement';
        $this->postJson($url)->assertOk()->assertJsonPath('registration.email_status', 'sent')
            ->assertJsonPath('registration.staff_notes', 'Keep tutor notes')->assertJsonPath('registration.version', 0);
        $this->postJson($url)->assertOk()->assertJsonPath('registration.email_status', 'sent');
        Mail::assertSent(RegistrationAcknowledgement::class, 1);
        $this->assertSame('new', $registration->fresh()->registration_status);
    }

    public function test_failed_acknowledgement_retry_is_truthfully_pending_and_keeps_registration(): void
    {
        Sanctum::actingAs($this->tutor);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated send failure'));
        $registration = $this->registration();
        $this->postJson('/api/admin/registrations/'.$registration->id.'/resend-acknowledgement')
            ->assertStatus(202)->assertJsonPath('registration.email_status', 'pending')
            ->assertJsonPath('registration.version', 0);
        $this->assertNull($registration->fresh()->acknowledged_at);
        $this->assertSame(1, CourseRegistration::count());
    }

    public function test_disabled_email_cannot_send_from_staff_retry(): void
    {
        config(['registration.mail_enabled' => false]);
        Sanctum::actingAs($this->tutor);
        Mail::fake();
        $registration = $this->registration();
        $this->postJson('/api/admin/registrations/'.$registration->id.'/resend-acknowledgement')
            ->assertStatus(202)->assertJsonPath('registration.email_enabled', false)
            ->assertJsonPath('registration.email_status', 'pending');
        Mail::assertNothingSent();
    }
}
