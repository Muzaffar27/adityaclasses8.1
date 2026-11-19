<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageItem extends Model
{
    protected $fillable = [
        'package_id',
        'subject_id',
        'grade_id',
        'price',
        'type',
        'three_month_price',
        'six_month_price',
        'twelve_month_price',
        'three_month_discount_percent',
        'six_month_discount_percent',
        'twelve_month_discount_percent',
    ];

    protected $casts = [
        'three_month_price' => 'decimal:2',
        'six_month_price' => 'decimal:2',
        'twelve_month_price' => 'decimal:2',
        'three_month_discount_percent' => 'decimal:2',
        'six_month_discount_percent' => 'decimal:2',
        'twelve_month_discount_percent' => 'decimal:2',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
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
