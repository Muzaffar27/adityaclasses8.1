<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LessonAccess extends Model
{
    protected $table = 'lesson_access';

    protected $fillable = [
        'subject_id',
        'grade_id',
        'user_id',
        'status',
        'duration_months',
        'requested_price',
        'expires_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'duration_months' => 3,
        'requested_price' => 0,
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'requested_price' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'accepted')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeLatestActiveForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)->active()
            ->whereIn('id', self::query()->selectRaw('MAX(id)')
                ->where('user_id', $userId)->active()
                ->groupBy('subject_id', 'grade_id'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
