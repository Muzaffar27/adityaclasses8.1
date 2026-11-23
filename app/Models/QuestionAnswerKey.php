<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionAnswerKey extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['answer' => 'array', 'validation' => 'array', 'question_snapshot' => 'array',
        'version' => 'integer', 'approved_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $key) {
            if (array_diff(array_keys($key->getDirty()), ['status', 'approved_by', 'approved_at', 'revoked_by', 'revoked_at', 'revocation_reason', 'updated_at'])) {
                throw new \LogicException('Answer key content is versioned and immutable. Create a new draft.');
            }
            if ($key->isDirty('status') && !in_array($key->status, ['draft' => ['approved', 'revoked'], 'approved' => ['revoked']][$key->getOriginal('status')] ?? [], true)) {
                throw new \LogicException('Invalid answer key transition.');
            }
        });
        static::deleting(function () { throw new \LogicException('Answer key history must be retained.'); });
    }
}
