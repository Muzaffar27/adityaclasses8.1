<?php

namespace App\Mail;

use App\Models\CourseRegistration;
use Illuminate\Mail\Mailable;

class RegistrationAcknowledgement extends Mailable
{
    public function __construct(public CourseRegistration $registration) {}

    public function build(): self
    {
        return $this->from(config('registration.mail_from_address'), config('registration.mail_from_name'))
            ->subject('Registration received - '.$this->registration->reference)
            ->view('emails.registration-acknowledgement');
    }
}
