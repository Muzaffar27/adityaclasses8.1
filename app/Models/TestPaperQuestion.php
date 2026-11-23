<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPaperQuestion extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['marking_snapshot'];
    protected $casts = ['content' => 'array', 'provenance' => 'array', 'marking_snapshot' => 'array', 'marks' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(function () { throw new \LogicException('Issued question and marking snapshots are immutable.'); });
        static::deleting(function () { throw new \LogicException('Issued question snapshots must be retained.'); });
    }
}
