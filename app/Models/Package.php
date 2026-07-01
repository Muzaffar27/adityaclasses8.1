<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name',
        'description',
        'base_price',
        'total_price',
        'three_month_price',
        'six_month_price',
        'nine_month_price',
        'grade_id',
        'subject_id',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'three_month_price' => 'decimal:2',
        'six_month_price' => 'decimal:2',
        'nine_month_price' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PackageItem::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
