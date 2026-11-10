<?php

namespace App\Http\Controllers;

use App\Models\CourseRegistration;
use App\Services\RegistrationAcknowledgementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseRegistrationController extends Controller
{
    public function store(Request $request, RegistrationAcknowledgementService $mail): JsonResponse
    {
        $catalogue = config('registration.catalogue');
        $grade = $request->input('grade');
        $courses = is_string($grade) ? ($catalogue[$grade]['courses'] ?? []) : [];
        $rules = [
            'submission_id' => ['required', 'uuid'],
            'studentName' => ['required', 'string', 'max:100'],
            'grade' => ['required', 'string', Rule::in(array_keys($catalogue))],
            'school' => ['required', 'string', 'max:150'],
            'returning' => ['required', Rule::in(['New student', 'Returning student'])],
            'subjects' => ['required', 'array', 'min:1', 'max:'.max(1, count($courses))],
            'subjects.*' => ['required', 'string', 'distinct', Rule::in(array_column($courses, 'name'))],
            'batches' => ['nullable', 'array:'.implode(',', array_keys($courses))],
            'guardian' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:22', 'regex:/^[+0-9 ()-]+$/', function ($attribute, $value, $fail) {
                if (strlen(preg_replace('/\D/', '', $value)) < 7) $fail('Enter a valid contact number.');
            }],
            'email' => ['required', 'email:rfc', 'max:200'],
            'message' => ['nullable', 'string', 'max:1000'],
            'consent' => ['required', 'accepted'],
            'updates' => ['required', 'boolean'],
        ];
        foreach ($courses as $id => $course) {
            $selected = in_array($course['name'], (array) $request->input('subjects'), true);
            $rules['batches.'.$id] = [Rule::requiredIf($selected && count($course['options']) > 1),
                'nullable', Rule::in(array_column($course['options'], 'id'))];
        }
        $data = $request->validate($rules);

        $submission = $data['submission_id'];
        unset($data['submission_id']);
        $choices = $data['batches'] ?? [];
        $data['batches'] = [];
        $data['timetable'] = [];
        foreach ($courses as $id => $course) {
            if (! in_array($course['name'], $data['subjects'], true)) continue;
            $option = count($course['options']) === 1 ? $course['options'][0]
                : collect($course['options'])->firstWhere('id', $choices[$id]);
            $data['batches'][$id] = $option['id'];
            foreach ($option['meetings'] as $meeting) {
                $data['timetable'][] = array_merge($meeting, ['subject' => $course['name'], 'batch' => $option['label']]);
            }
        }
        // Snapshot authoritative prices and schedules, never a client-provided total.
        $data['monthly_fee_per_subject'] = $catalogue[$grade]['fee'];
        $data['monthly_total'] = count($data['subjects']) * $catalogue[$grade]['fee'];
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
        $registration = CourseRegistration::firstOrCreate(['submission_id' => $submission], [
            'reference' => 'AC-2027-'.strtoupper((string) Str::ulid()),
            'payload_hash' => $hash, 'details' => $data,
        ]);

        if (! hash_equals($registration->payload_hash, $hash)) {
            return response()->json(['message' => 'This submission was already used. Start another registration to submit different details.'], 409);
        }

        // Save first. An SMTP failure must never discard a registration.
        $sent = $registration->acknowledged_at !== null;
        if ($registration->wasRecentlyCreated) $sent = $mail->send($registration);

        return response()->json([
            'reference' => $registration->reference,
            'email_status' => $sent ? 'sent' : 'pending',
            'email_enabled' => (bool) config('registration.mail_enabled', false),
        ], $registration->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'no-store');
    }
}
