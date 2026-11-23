<?php

namespace App\Console\Commands;

use App\Models\CourseRegistration;
use App\Services\RegistrationAcknowledgementService;
use Illuminate\Console\Command;

class SendRegistrationAcknowledgements extends Command
{
    protected $signature = 'registration:send-acknowledgements {--reference= : Retry one registration reference}';
    protected $description = 'Send saved registration acknowledgements that have not yet been sent';

    public function handle(RegistrationAcknowledgementService $mail): int
    {
        $failed = 0;
        CourseRegistration::whereNull('acknowledged_at')
            ->when($this->option('reference'), fn ($query, $reference) => $query->where('reference', $reference))
            ->chunkById(50, function ($registrations) use ($mail, &$failed) {
                foreach ($registrations as $registration) {
                    if (! $mail->send($registration)) $failed++;
                }
            });
        $this->info($failed ? "$failed acknowledgement(s) remain pending." : 'Pending acknowledgements processed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
