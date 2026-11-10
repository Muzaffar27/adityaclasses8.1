<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseRegistration extends Model
{
    protected $fillable = ['submission_id', 'reference', 'payload_hash', 'details', 'acknowledged_at'];

    protected $casts = ['details' => 'array', 'acknowledged_at' => 'datetime', 'workflow_version' => 'integer',
        'staff_updated_at' => 'datetime', 'payment_verified_at' => 'datetime'];

    public function staffUpdatedBy()
    {
        return $this->belongsTo(User::class, 'staff_updated_by');
    }

    public function paymentVerifiedBy()
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }
}
