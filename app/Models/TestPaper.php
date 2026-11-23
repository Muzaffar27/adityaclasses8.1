<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPaper extends Model
{
    public const TRANSITIONS = [
        'generated' => ['available', 'cancelled'],
        'available' => ['started', 'cancelled'],
        'started' => ['submitted', 'cancelled'],
        'submitted' => ['awaiting_review', 'marked'],
        'awaiting_review' => ['marked'],
        'marked' => [], 'cancelled' => [],
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['marking_ready' => 'boolean', 'available_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $paper) {
            if (array_diff(array_keys($paper->getDirty()), ['status', 'available_at', 'cancelled_at', 'updated_at'])) {
                throw new \LogicException('Issued paper identity and blueprint are immutable.');
            }
            if ($paper->isDirty('status') && !in_array($paper->status, self::TRANSITIONS[$paper->getOriginal('status')] ?? [], true)) {
                throw new \LogicException('Invalid paper status transition.');
            }
        });
        static::deleting(function () { throw new \LogicException('Paper history must be retained.'); });
    }

    public function batch() { return $this->belongsTo(AssessmentBatch::class, 'batch_id'); }
    public function questions() { return $this->hasMany(TestPaperQuestion::class, 'paper_id')->orderBy('position'); }
    public function attempt() { return $this->hasOne(TestPaperAttempt::class, 'paper_id'); }
    public function events() { return $this->hasMany(TestPaperEvent::class, 'paper_id')->orderBy('id'); }
}
