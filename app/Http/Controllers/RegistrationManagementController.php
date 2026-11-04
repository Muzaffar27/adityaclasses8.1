<?php

namespace App\Http\Controllers;

use App\Models\CourseRegistration;
use App\Services\RegistrationAcknowledgementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RegistrationManagementController extends Controller
{
    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['tutor', 'admin'], true), 403);
        abort_unless(Schema::hasColumn('course_registrations', 'registration_status'), 503,
            'Registration management setup is pending.');
    }

    private function filtered(Request $request): Builder
    {
        $subjects = collect(config('registration.catalogue'))->flatMap(fn ($grade) => array_column($grade['courses'], 'name'))->unique()->values()->all();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'new', 'contacted', 'enrolled', 'all'])],
            'grade' => ['nullable', Rule::in(array_keys(config('registration.catalogue')))],
            'subject' => ['nullable', Rule::in($subjects)],
            'payment' => ['nullable', Rule::in(['unverified', 'verified'])],
            'email' => ['nullable', Rule::in(['pending', 'sent'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'student'])],
        ]);
        $query = CourseRegistration::query();
        $status = $filters['status'] ?? 'pending';
        if ($status === 'pending') $query->whereIn('registration_status', ['new', 'contacted']);
        elseif ($status !== 'all') $query->where('registration_status', $status);
        if ($filters['grade'] ?? null) $query->where('details->grade', $filters['grade']);
        if ($filters['subject'] ?? null) $query->whereJsonContains('details->subjects', $filters['subject']);
        if ($filters['payment'] ?? null) $query->where('payment_status', $filters['payment']);
        if (($filters['email'] ?? null) === 'pending') $query->whereNull('acknowledged_at');
        elseif (($filters['email'] ?? null) === 'sent') $query->whereNotNull('acknowledged_at');
        if ($search = $filters['search'] ?? null) {
            $query->where(function (Builder $query) use ($search) {
                foreach (['reference', 'details->studentName', 'details->guardian', 'details->email', 'details->phone', 'details->school'] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'student' => $query->orderBy('details->studentName')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    private function summary(CourseRegistration $registration): array
    {
        $details = $registration->details;
        return [
            'id' => $registration->id, 'reference' => $registration->reference,
            'submitted_at' => $registration->created_at->toIso8601String(),
            'student' => $details['studentName'], 'grade' => $details['grade'],
            'guardian' => $details['guardian'], 'email' => $details['email'],
            'subjects' => $details['subjects'], 'monthly_total' => $details['monthly_total'] ?? null,
            'registration_status' => $registration->registration_status,
            'payment_status' => $registration->payment_status,
            'email_status' => $registration->acknowledged_at ? 'sent' : 'pending',
            'email_enabled' => (bool) config('registration.mail_enabled', false),
        ];
    }

    private function detail(CourseRegistration $registration): array
    {
        $registration->load(['staffUpdatedBy:id,name', 'paymentVerifiedBy:id,name']);
        return array_merge($this->summary($registration), [
            'details' => $registration->details, 'staff_notes' => $registration->staff_notes,
            'version' => $registration->workflow_version,
            'acknowledged_at' => $registration->acknowledged_at?->toIso8601String(),
            'staff_updated_at' => $registration->staff_updated_at?->toIso8601String(),
            'staff_updated_by' => $registration->staffUpdatedBy?->name,
            'payment_verified_at' => $registration->payment_verified_at?->toIso8601String(),
            'payment_verified_by' => $registration->paymentVerifiedBy?->name,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizeStaff($request);
        $page = $this->filtered($request)->paginate($request->integer('per_page', 15));
        $totals = CourseRegistration::select('registration_status', DB::raw('count(*) as total'))
            ->groupBy('registration_status')->pluck('total', 'registration_status');
        return response()->json([
            'data' => $page->getCollection()->map(fn ($registration) => $this->summary($registration)),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'per_page' => $page->perPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
            'counts' => ['new' => (int) ($totals['new'] ?? 0), 'contacted' => (int) ($totals['contacted'] ?? 0),
                'enrolled' => (int) ($totals['enrolled'] ?? 0),
                'pending' => (int) ($totals['new'] ?? 0) + (int) ($totals['contacted'] ?? 0)],
            'grades' => array_keys(config('registration.catalogue')),
            'subjects' => collect(config('registration.catalogue'))->flatMap(fn ($grade) => array_column($grade['courses'], 'name'))->unique()->sort()->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $id)
    {
        $this->authorizeStaff($request);
        return response()->json($this->detail(CourseRegistration::findOrFail($id)))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeStaff($request);
        $registration = CourseRegistration::findOrFail($id);
        $data = $request->validate([
            'registration_status' => ['required', Rule::in(['new', 'contacted', 'enrolled'])],
            'payment_status' => ['required', Rule::in(['unverified', 'verified'])],
            'staff_notes' => ['nullable', 'string', 'max:5000'],
            'version' => ['required', 'integer', 'min:0'],
            'confirm_payment' => $request->input('payment_status') === 'verified' && $registration->payment_status !== 'verified'
                ? ['required', 'accepted'] : ['sometimes', 'boolean'],
        ]);
        $verified = $data['payment_status'] === 'verified';
        $wasVerified = $registration->payment_status === 'verified';
        $changes = [
            'registration_status' => $data['registration_status'], 'payment_status' => $data['payment_status'],
            'staff_notes' => $data['staff_notes'] ?? null,
            'staff_updated_by' => $request->user()->id, 'staff_updated_at' => now(),
            'payment_verified_at' => $verified ? ($wasVerified ? $registration->payment_verified_at : now()) : null,
            'payment_verified_by' => $verified ? ($wasVerified ? $registration->payment_verified_by : $request->user()->id) : null,
            'workflow_version' => DB::raw('workflow_version + 1'), 'updated_at' => now(),
        ];
        if (! CourseRegistration::whereKey($id)->where('workflow_version', $data['version'])->update($changes)) {
            return response()->json(['message' => 'Another tutor updated this registration. Your edits are still here. Reload the saved details before saving again.'], 409)
                ->header('Cache-Control', 'private, no-store');
        }
        return response()->json($this->detail($registration->fresh()))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request)
    {
        $this->authorizeStaff($request);
        $query = $this->filtered($request);
        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            // Prevent untrusted form values from becoming spreadsheet formulas.
            $safe = static fn ($value) => preg_match('/^[\s]*[=+\-@]/u', (string) $value) ? "'".$value : $value;
            fputcsv($stream, ['Reference', 'Submitted', 'Student', 'Grade', 'School', 'Returning', 'Guardian',
                'Phone', 'Email', 'Subjects', 'Timetable', 'Monthly tuition', 'Registration status', 'Payment status',
                'Email status', 'Parent notes', 'Contact consent', 'Optional updates consent', 'Internal notes']);
            $query->chunk(200, function ($registrations) use ($stream, $safe) {
                foreach ($registrations as $registration) {
                    $details = $registration->details;
                    $timetable = collect($details['timetable'] ?? [])->map(fn ($item) => implode(' · ', array_filter([
                        $item['subject'], $item['batch'] ?? null, $item['note'] ?? null,
                        $item['day'], $item['time'], 'Starts '.$item['start'],
                    ])))->implode('; ');
                    fputcsv($stream, array_map($safe, [$registration->reference, $registration->created_at->toIso8601String(),
                        $details['studentName'], $details['grade'], $details['school'], $details['returning'], $details['guardian'],
                        $details['phone'], $details['email'], implode('; ', $details['subjects']), $timetable,
                        $details['monthly_total'] ?? '', $registration->registration_status, $registration->payment_status,
                        $registration->acknowledged_at ? 'sent' : 'pending', $details['message'] ?? '',
                        ! empty($details['consent']) ? 'Yes' : 'No', ! empty($details['updates']) ? 'Yes' : 'No', $registration->staff_notes ?? '']));
                }
            });
            fclose($stream);
        }, 'registrations-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function resendAcknowledgement(Request $request, int $id, RegistrationAcknowledgementService $mail)
    {
        $this->authorizeStaff($request);
        $registration = CourseRegistration::findOrFail($id);
        $alreadySent = $registration->acknowledged_at !== null;
        if (! $alreadySent) $mail->send($registration);
        $registration->refresh();
        $sent = $registration->acknowledged_at !== null;
        return response()->json([
            'registration' => $this->detail($registration),
            'message' => $alreadySent ? 'This acknowledgement was already sent. No duplicate email was sent.'
                : (! config('registration.mail_enabled', false) ? 'Email sending is currently disabled. The registration remains saved.'
                : ($sent ? 'Acknowledgement email sent.' : 'The email is still pending. Check the email setup before retrying.')),
        ], $sent ? 200 : 202)->header('Cache-Control', 'private, no-store');
    }
}
