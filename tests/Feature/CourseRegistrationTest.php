<?php

namespace Tests\Feature;

use App\Mail\RegistrationAcknowledgement;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'registration.mail_enabled' => true]);
        $this->app->forgetInstance(\Illuminate\Cache\RateLimiter::class);
        $this->withSession(['_token' => str_repeat('t', 40)])
            ->withHeader('X-CSRF-TOKEN', str_repeat('t', 40));
        config(['database.default' => 'registration_testing', 'database.connections.registration_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_11_04_000001_create_course_registrations_table.php'))->up();
        (require database_path('migrations/2026_11_04_000002_add_staff_workflow_to_course_registrations.php'))->up();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::purge('registration_testing');
        parent::tearDown();
    }

    private function payload(): array
    {
        return [
            'submission_id' => (string) Str::uuid(), 'studentName' => 'Test Student',
            'grade' => 'Grade 8', 'school' => 'Test School', 'returning' => 'New student',
            'subjects' => ['English', 'Mathematics'], 'batches' => ['mathematics' => 'batch-2'],
            'guardian' => 'Test Guardian', 'phone' => '5000 0000', 'email' => 'guardian@example.test',
            'message' => 'Registration test', 'consent' => true, 'updates' => false,
        ];
    }

    public function test_public_page_shows_real_payment_details_without_account_preview(): void
    {
        $this->get('/registration')->assertOk()->assertSee('59473797')
            ->assertSee('csrf-token')->assertDontSee('Sample payment number')
            ->assertDontSee('Set your password')->assertDontSee('Preview welcome email');
    }

    public function test_submission_saves_choices_and_sends_acknowledgement(): void
    {
        $payload = $this->payload();
        $response = $this->postJson('/registration', $payload)->assertCreated()->assertJsonPath('email_status', 'sent');
        $registration = CourseRegistration::sole();
        $this->assertSame($payload['subjects'], $registration->details['subjects']);
        $this->assertSame('5000 0000', $registration->details['phone']);
        $this->assertSame('batch-2', $registration->details['batches']['mathematics']);
        $this->assertSame('Wednesday', $registration->details['timetable'][1]['day']);
        $this->assertSame(1000, $registration->details['monthly_total']);
        $this->assertNotNull($registration->acknowledged_at);
        $this->assertSame($registration->reference, $response->json('reference'));
        $this->assertArrayNotHasKey('email', $response->json());
        Mail::assertSent(RegistrationAcknowledgement::class, fn ($mail) => $mail->hasTo($payload['email']));
        $built = (new RegistrationAcknowledgement($registration))->build();
        $this->assertTrue($built->hasFrom('info@adityaclasses.mu'));
    }

    public function test_retry_returns_same_reference_without_duplicate_registration_or_email(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/registration', $payload)->assertCreated()->json('reference');
        $this->postJson('/registration', $payload)->assertOk()->assertJsonPath('reference', $first);
        $this->assertSame(1, CourseRegistration::count());
        Mail::assertSentCount(1);
        $payload['studentName'] = 'Different Student';
        $this->postJson('/registration', $payload)->assertConflict();
        $this->assertSame('Test Student', CourseRegistration::sole()->details['studentName']);
    }

    public function test_invalid_grade_subject_batch_contact_or_consent_are_rejected(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach ([
            ['grade' => 'Grade 6'], ['subjects' => []], ['subjects' => ['Science']],
            ['subjects' => ['English', 'English']], ['batches' => null], ['batches' => ['mathematics' => 'batch-3']],
            ['batches' => ['mathematics' => 'batch-2', 'science' => 'standard']],
            ['email' => 'invalid'], ['phone' => '123'], ['phone' => '<script>'],
            ['consent' => false], ['studentName' => '   '], ['submission_id' => 'invalid'],
        ] as $invalid) {
            $this->postJson('/registration', array_replace($this->payload(), $invalid))->assertUnprocessable();
        }
        $this->assertSame(0, CourseRegistration::count());
        Mail::assertNothingSent();
    }

    public function test_other_grades_save_their_actual_timetable_and_ignore_client_prices(): void
    {
        $this->postJson('/registration', array_replace($this->payload(), [
            'grade' => 'Grade 9', 'subjects' => ['Science'], 'batches' => [], 'monthly_total' => 1,
        ]))->assertCreated();
        $details = CourseRegistration::sole()->details;
        $this->assertSame(['science' => 'standard'], $details['batches']);
        $this->assertSame(600, $details['monthly_total']);
        $this->assertSame('Sunday', $details['timetable'][0]['day']);
        $this->assertSame('8:00–10:00 am', $details['timetable'][0]['time']);
    }

    public function test_every_grade_uses_the_fee_sheet_and_only_its_offered_subjects(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach ([7 => 400, 8 => 500, 9 => 600, 10 => 700, 11 => 800, 12 => 1000, 13 => 1100] as $grade => $fee) {
            $subjects = $grade >= 12 ? ['English General Paper', 'Mathematics (Main)'] : ['English', 'Mathematics'];
            $payload = array_replace($this->payload(), ['grade' => 'Grade '.$grade, 'subjects' => $subjects,
                'batches' => $grade === 7 || $grade >= 12 ? [] : ['mathematics' => 'batch-1', 'english' => $grade === 9 ? 'batch-1' : 'standard']]);
            $reference = $this->postJson('/registration', $payload)->assertCreated()->json('reference');
            $details = CourseRegistration::where('reference', $reference)->firstOrFail()->details;
            $this->assertSame($fee, $details['monthly_fee_per_subject']);
            $this->assertSame(2 * $fee, $details['monthly_total']);
            $this->assertNotEmpty($details['timetable']);
        }
        $this->postJson('/registration', array_replace($this->payload(), ['grade' => 'Grade 7', 'subjects' => ['Physics'], 'batches' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('subjects.0');
    }

    public function test_grade_13_main_maths_keeps_all_sessions_and_is_charged_once(): void
    {
        $this->postJson('/registration', array_replace($this->payload(), [
            'grade' => 'Grade 13', 'subjects' => ['Mathematics (Main)'], 'batches' => [],
        ]))->assertCreated();
        $details = CourseRegistration::sole()->details;
        $this->assertSame(1100, $details['monthly_total']);
        $this->assertCount(3, $details['timetable']);
        $this->assertSame('12 April 2027', $details['timetable'][1]['start']);
        $this->assertSame('Mechanics · M1', $details['timetable'][1]['note']);
    }

    public function test_all_offered_grade_10_subjects_and_independent_batch_choices_can_be_saved(): void
    {
        $this->postJson('/registration', array_replace($this->payload(), [
            'grade' => 'Grade 10', 'subjects' => array_column(config('registration.catalogue.Grade 10.courses'), 'name'),
            'batches' => ['mathematics' => 'batch-2', 'additional-mathematics' => 'batch-1', 'accounts' => 'batch-2'],
        ]))->assertCreated();
        $details = CourseRegistration::sole()->details;
        $this->assertCount(9, $details['subjects']);
        $this->assertSame(6300, $details['monthly_total']);
        $this->assertSame('Sunday', $details['timetable'][2]['day']);
        $this->assertSame('10:00–11:45 am', $details['timetable'][2]['time']);
    }

    public function test_mail_failure_keeps_registration_and_allows_operator_retry(): void
    {
        $manager = new \Illuminate\Mail\MailManager($this->app);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated SMTP failure'));
        $response = $this->postJson('/registration', $this->payload())->assertCreated()->assertJsonPath('email_status', 'pending');
        $registration = CourseRegistration::sole();
        $this->assertNull($registration->acknowledged_at);
        Mail::swap($manager);
        Mail::fake();
        $this->artisan('registration:send-acknowledgements', ['--reference' => $response->json('reference')])->assertSuccessful();
        $this->assertNotNull($registration->fresh()->acknowledged_at);
        Mail::assertSentCount(1);
        $this->artisan('registration:send-acknowledgements')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_acknowledgement_escapes_input_and_contains_reference_payment_and_choices(): void
    {
        $this->postJson('/registration', array_replace($this->payload(), ['studentName' => '<script>alert(1)</script>']))->assertCreated();
        $registration = CourseRegistration::sole();
        $html = (new RegistrationAcknowledgement($registration))->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        foreach ([$registration->reference, '59473797', 'English, Mathematics', 'Wednesday',
            '6 January 2027', 'Monthly tuition: Rs 1,000', 'not a payment receipt'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('password', $html);
    }

    public function test_public_submission_is_rate_limited(): void
    {
        $payload = $this->payload();
        for ($i = 0; $i < 6; $i++) $this->postJson('/registration', $payload)->assertSuccessful();
        $this->postJson('/registration', $payload)->assertStatus(429);
        $this->assertSame(1, CourseRegistration::count());
    }

    public function test_public_registration_cannot_set_staff_statuses_or_internal_notes(): void
    {
        $this->postJson('/registration', array_replace($this->payload(), [
            'registration_status' => 'enrolled', 'payment_status' => 'verified',
            'staff_notes' => 'Public override', 'payment_verified_by' => 1, 'workflow_version' => 99,
        ]))->assertCreated();
        $saved = CourseRegistration::sole();
        $this->assertSame('new', $saved->registration_status);
        $this->assertSame('unverified', $saved->payment_status);
        $this->assertNull($saved->staff_notes);
        $this->assertNull($saved->payment_verified_by);
        $this->assertSame(0, $saved->workflow_version);
        $this->assertArrayNotHasKey('staff_notes', $saved->details);
    }

    public function test_disabled_email_saves_registration_without_attempting_delivery(): void
    {
        config(['registration.mail_enabled' => false]);
        $this->postJson('/registration', $this->payload())->assertCreated()
            ->assertJsonPath('email_status', 'pending')->assertJsonPath('email_enabled', false);
        $this->assertSame(1, CourseRegistration::count());
        $this->assertNull(CourseRegistration::sole()->acknowledged_at);
        Mail::assertNothingSent();
    }
}
