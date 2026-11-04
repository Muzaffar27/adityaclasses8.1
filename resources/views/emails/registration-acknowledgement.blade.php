<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f1f5f9;color:#172554;font-family:Arial,sans-serif;line-height:1.6">
<main style="max-width:600px;margin:24px auto;padding:28px;background:white;border-radius:16px">
    <p style="color:#4f46e5;font-weight:bold">ADITYA CLASSES · 2027 REGISTRATION</p>
    <h1 style="font-size:26px">We’ve received your registration.</h1>
    <p>Hello {{ $registration->details['guardian'] }},</p>
    <p>Thank you for registering {{ $registration->details['studentName'] }} with Aditya Classes.</p>
    <p><strong>Registration reference:</strong><br>{{ $registration->reference }}</p>
    <p><strong>Student:</strong> {{ $registration->details['studentName'] }}<br>
    <strong>Grade:</strong> {{ $registration->details['grade'] }}<br>
    <strong>Subjects:</strong> {{ implode(', ', $registration->details['subjects']) }}<br>
    @if($registration->details['mathsBatch'] ?? null)<strong>Maths batch:</strong> {{ $registration->details['mathsBatch'] }}<br>@endif
    @if($registration->details['session'] ?? null)<strong>Preferred time:</strong> {{ $registration->details['session'] }}@endif</p>
    @if($registration->details['timetable'] ?? null)
    <h2 style="font-size:20px">Your timetable</h2>
    @foreach($registration->details['timetable'] as $meeting)
    <p><strong>{{ $meeting['subject'] }}{{ $meeting['batch'] ? ' · '.$meeting['batch'] : '' }}</strong><br>
    @if($meeting['note']){{ $meeting['note'] }}<br>@endif
    {{ $meeting['day'] }} · {{ $meeting['time'] }}<br>Starts {{ $meeting['start'] }}</p>
    @endforeach
    @endif
    @if(isset($registration->details['monthly_total']))
    <p><strong>Monthly tuition: Rs {{ number_format($registration->details['monthly_total']) }}</strong><br>
    {{ count($registration->details['subjects']) }} subject(s) × Rs {{ number_format($registration->details['monthly_fee_per_subject']) }} per month.</p>
    @endif
    <h2 style="font-size:20px">Payment details</h2>
    <p><strong>Juice number:</strong> {{ config('registration.payment_number') }}<br>
    <strong>Recipient:</strong> {{ config('registration.payment_recipient') }}<br>
    <strong>Payment reference:</strong> {{ $registration->details['studentName'] }} + {{ $registration->details['grade'] }}</p>
    <p>This email acknowledges your registration. It is not a payment receipt or confirmation that payment has been received.</p>
    <p>Keep your registration reference for any questions. You can reply to this email to contact us.</p>
    <p>Thank you,<br><strong>The Aditya Classes team</strong></p>
</main></body></html>
