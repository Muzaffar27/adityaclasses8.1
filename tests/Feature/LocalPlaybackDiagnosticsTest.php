<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LocalPlaybackDiagnosticsTest extends TestCase
{
    private function payload(): array
    {
        return ['type' => 'local-playback-diagnostic', 'run_id' => '00000000-0000-4000-8000-000000000001',
            'tab_id' => '00000000-0000-4000-8000-000000000002', 'lesson_id' => 387, 'video_type' => 'lesson',
            'events' => [['stage' => 'player-error', 'at' => '2026-11-10T12:00:00Z',
                'message' => 'Failed https://example.test/private?secret=hidden', 'httpStatus' => 403, 'token' => 'must-not-log']]];
    }
    public function test_diagnostics_are_rejected_outside_local_environment_or_host(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('http://localhost/api/client-error', $this->payload())->assertNotFound();
        $this->app->detectEnvironment(fn () => 'local');
        $this->postJson('https://example.test/api/client-error', $this->payload())->assertNotFound();
    }
    public function test_local_events_are_bounded_and_secrets_and_urls_are_not_logged(): void
    {
        $this->withoutExceptionHandling();
        $this->app->detectEnvironment(fn () => 'local');
        $logger = \Mockery::mock(\Illuminate\Log\Logger::class);
        Log::shouldReceive('build')->once()->andReturn($logger);
        $logger->shouldReceive('info')->once()->withArgs(function ($message, $context) {
            $this->assertSame('local-playback', $message);
            $this->assertArrayNotHasKey('token', $context['events'][0]);
            $this->assertSame('Failed [url]', $context['events'][0]['message']);
            $this->assertSame(403, $context['events'][0]['httpStatus']);
            return true;
        });
        $this->postJson('http://localhost/api/client-error', $this->payload())->assertOk()->assertJsonPath('logged', true);
    }
    public function test_oversized_event_batches_are_rejected(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $payload = $this->payload();
        $payload['events'] = array_fill(0, 21, $payload['events'][0]);
        $this->postJson('http://localhost/api/client-error', $payload)->assertUnprocessable()->assertJsonValidationErrors('events');
    }
}
