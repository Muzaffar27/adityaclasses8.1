<?php

namespace App\Services;

use App\Mail\RegistrationAcknowledgement;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegistrationAcknowledgementService
{
    public function send(CourseRegistration $registration): bool
    {
        $lock = null;
        try {
            $registration->refresh();
            if ($registration->acknowledged_at) return true;
            if (! config('registration.mail_enabled', false)) return false;
            $lock = Cache::lock('registration-mail:'.$registration->id, 120);
            if (! $lock->get()) return false;
            $registration->refresh();
            if ($registration->acknowledged_at) return true;

            // A development log/array transport must never be reported as real delivery.
            $transport = config('mail.mailers.'.config('mail.default').'.transport');
            if (app()->environment('production') && in_array($transport, ['log', 'array'], true)) {
                return false;
            }

            config(['mail.mailers.smtp.timeout' => config('registration.mail_timeout', 15)]);
            Mail::to($registration->details['email'])->send(new RegistrationAcknowledgement($registration));
            $registration->update(['acknowledged_at' => now()]);

            return true;
        } catch (Throwable $exception) {
            // Do not log the payload, email address, SMTP credentials or provider response.
            Log::warning('Registration acknowledgement pending.', [
                'reference' => $registration->reference, 'error_type' => get_class($exception),
            ]);

            return false;
        } finally {
            $lock?->release();
        }
    }
}
