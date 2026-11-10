<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light">
<title>Registration received - Aditya Classes</title>
<style>@media only screen and (max-width:600px){.email-padding{padding-left:20px!important;padding-right:20px!important}}</style>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;color:#171717;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">Your registration details and reservation payment instructions.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #e5e5e5;border-radius:12px;">
<tr><td class="email-padding" style="padding:22px 28px;">
<h1 style="margin:0 0 12px;font-size:24px;line-height:30px;color:#171717;">Registration received</h1>
<p style="margin:0 0 18px;font-size:14px;line-height:22px;color:#525252;">Hello {{ $registration->details['guardian'] }}, thank you for registering with Aditya Classes.</p>
<p style="margin:0 0 4px;font-size:15px;line-height:22px;color:#171717;"><strong>{{ $registration->details['studentName'] }}</strong> &middot; {{ $registration->details['grade'] }}</p>
<p style="margin:0 0 14px;font-size:14px;line-height:21px;color:#525252;">{{ implode(', ', $registration->details['subjects']) }}</p>
@if($registration->details['mathsBatch'] ?? null)
<p style="margin:0 0 6px;font-size:13px;line-height:20px;color:#525252;">Maths batch: {{ $registration->details['mathsBatch'] }}</p>
@endif
@if($registration->details['session'] ?? null)
<p style="margin:0 0 6px;font-size:13px;line-height:20px;color:#525252;">Preferred time: {{ $registration->details['session'] }}</p>
@endif
@if($registration->details['timetable'] ?? null)
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #e5e5e5;">
@foreach($registration->details['timetable'] as $meeting)
<tr><td style="padding:9px 0;border-bottom:1px solid #e5e5e5;font-size:13px;line-height:20px;color:#525252;">
<strong style="color:#171717;">{{ $meeting['subject'] }}{{ $meeting['batch'] ? ' - '.$meeting['batch'] : '' }}</strong><br>
{{ $meeting['day'] }} &middot; {{ $meeting['time'] }}<br>
<span style="color:#171717;">Starts {{ $meeting['start'] }}@if($meeting['note']) &middot; {{ $meeting['note'] }}@endif</span>
</td></tr>
@endforeach
</table>
@endif
@if(isset($registration->details['monthly_total']))
<p style="margin:16px 0 4px;font-size:16px;line-height:24px;font-weight:bold;color:#171717;">Monthly tuition: Rs {{ number_format($registration->details['monthly_total']) }}</p>
@endif
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;background-color:#ffffff;border:1px solid #e7c4c9;border-left:3px solid #8b1538;border-radius:8px;"><tr><td style="padding:16px;">
<p style="margin:0 0 8px;font-size:14px;line-height:22px;color:#171717;"><strong>Reserve your place with a 50% payment.</strong><br>The remaining 50% is due when classes begin.</p>
<p style="margin:0 0 8px;font-size:15px;line-height:23px;color:#8b1538;"><strong>Juice: {{ config('registration.payment_number') }}</strong><br><span style="font-size:13px;color:#525252;">{{ config('registration.payment_recipient') }}</span></p>
<p style="margin:0;font-size:12px;line-height:19px;color:#525252;">Payment reference: {{ $registration->details['studentName'] }} + {{ $registration->details['grade'] }}</p>
</td></tr></table>
<p style="margin:14px 0 0;font-size:12px;line-height:19px;color:#525252;">This is a registration acknowledgement, not a payment receipt.</p>
</td></tr>
<tr><td class="email-padding" style="padding:14px 28px;border-top:1px solid #e5e5e5;">
<p style="margin:0;font-size:12px;line-height:19px;color:#525252;">Please do not reply to this email. For assistance with your registration, contact us on WhatsApp at <a href="https://wa.me/230{{ config('registration.payment_number') }}" style="color:#171717;font-weight:bold;text-decoration:underline;">{{ config('registration.payment_number') }}</a>.<br><strong style="color:#171717;">Aditya Classes</strong></p>
<p style="margin:14px 0 3px;font-size:10px;line-height:16px;letter-spacing:1px;color:#525252;">REGISTRATION REFERENCE</p>
<p style="margin:0;font-family:Consolas,'Courier New',monospace;font-size:12px;line-height:19px;overflow-wrap:anywhere;word-break:break-all;color:#171717;">{{ $registration->reference }}</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
