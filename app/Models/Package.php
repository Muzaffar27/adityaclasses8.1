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
        'twelve_month_price',
        'three_month_discount_percent',
        'six_month_discount_percent',
        'twelve_month_discount_percent',
        'grade_id',
        'subject_id',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'three_month_price' => 'decimal:2',
        'six_month_price' => 'decimal:2',
        'twelve_month_price' => 'decimal:2',
        'three_month_discount_percent' => 'decimal:2',
        'six_month_discount_percent' => 'decimal:2',
        'twelve_month_discount_percent' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PackageItem::class);
    }

    public function pricesForDuration(int $months): array
    {
        $term = [3 => 'three_month', 6 => 'six_month', 12 => 'twelve_month'][$months];
        $items = $this->items->sortBy('id')->values();
        $weight = (float) $items->sum('price');
        $price = (float) $this->{$term.'_price'};
        // Keep the existing zero-price fallback for packages already on sale.
        $price = $price > 0 ? $price : $weight;
        $cents = (int) round($price * (1 - (float) $this->{$term.'_discount_percent'} / 100) * 100);
        $prices = [];
        $accumulated = 0;
        $allocated = 0;
        foreach ($items as $item) {
            $accumulated += $weight > 0 ? (float) $item->price / $weight : 1 / $items->count();
            $next = (int) round($cents * $accumulated);
            $prices[$item->id] = ($next - $allocated) / 100;
            $allocated = $next;
        }
        return $prices;
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
