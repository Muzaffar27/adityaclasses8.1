<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            foreach (['three_month', 'six_month', 'twelve_month'] as $term) {
                // Nullable fields retain compatibility with older package payloads.
                $table->decimal($term.'_price', 10, 2)->nullable();
                $table->decimal($term.'_discount_percent', 5, 2)->nullable();
            }
        });

        // Populate only new columns. Existing payable amounts, discounts and access stay intact.
        DB::table('packages')->orderBy('id')->chunkById(100, function ($packages) {
            foreach ($packages as $package) {
                $items = DB::table('package_items')->where('package_id', $package->id)->orderBy('id')->get();
                $weight = (float) $items->sum('price');
                foreach (['three_month', 'six_month', 'twelve_month'] as $term) {
                    $gross = (float) $package->{$term.'_price'};
                    $gross = $gross > 0 ? $gross : $weight;
                    $discount = (float) $package->{$term.'_discount_percent'};
                    $netCents = (int) round($gross * (1 - $discount / 100) * 100);
                    $accumulated = 0;
                    $allocated = 0;
                    $grossAllocated = 0;
                    foreach ($items as $item) {
                        $accumulated += $weight > 0 ? (float) $item->price / $weight : 1 / $items->count();
                        $next = (int) round($netCents * $accumulated);
                        $grossNext = (int) round($gross * 100 * $accumulated);
                        // Derive a cent-based list price that retains the exact discounted amount.
                        $price = $discount < 100
                            ? round((($next - $allocated) / 100) / (1 - $discount / 100), 2)
                            : ($grossNext - $grossAllocated) / 100;
                        DB::table('package_items')->where('id', $item->id)->update([
                            $term.'_price' => $price,
                            $term.'_discount_percent' => $discount,
                        ]);
                        $allocated = $next;
                        $grossAllocated = $grossNext;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            foreach (['three_month', 'six_month', 'twelve_month'] as $term) {
                $table->dropColumn([$term.'_price', $term.'_discount_percent']);
            }
        });
    }
};
