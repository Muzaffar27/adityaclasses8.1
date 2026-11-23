<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseRegistration extends Model
{
    protected $fillable = ['submission_id', 'reference', 'payload_hash', 'details', 'acknowledged_at'];

    protected $casts = ['details' => 'array', 'acknowledged_at' => 'datetime', 'workflow_version' => 'integer',
        'staff_updated_at' => 'datetime', 'payment_verified_at' => 'datetime'];

    public static function attendanceLabel(?string $mode): string
    {
        return match ($mode) {
            'online' => 'Online',
            'face_to_face' => 'Face-to-face',
            default => 'Not specified',
        };
    }

    public function subjectAttendance(): array
    {
        $meetings = collect($this->details['timetable'] ?? []);
        return collect($this->details['subjects'] ?? [])->mapWithKeys(fn ($subject) => [
            $subject => self::attendanceLabel($meetings->firstWhere('subject', $subject)['attendance_mode'] ?? null),
        ])->all();
    }

    public function staffUpdatedBy()
    {
        return $this->belongsTo(User::class, 'staff_updated_by');
    }

    public function paymentVerifiedBy()
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }
}
