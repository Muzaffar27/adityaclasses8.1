<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentBatch extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['topics' => 'array'];

    protected static function booted(): void
    {
        static::updating(function () { throw new \LogicException('Issued assessment batch scope is immutable.'); });
        static::deleting(function () { throw new \LogicException('Assessment batch history must be retained.'); });
    }

    public function papers() { return $this->hasMany(TestPaper::class, 'batch_id'); }
}
