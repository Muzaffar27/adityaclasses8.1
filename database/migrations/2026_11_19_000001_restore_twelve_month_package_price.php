<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('twelve_month_price', 10, 2)->default(0);
            $table->decimal('three_month_discount_percent', 5, 2)->default(0);
            $table->decimal('six_month_discount_percent', 5, 2)->default(0);
            $table->decimal('twelve_month_discount_percent', 5, 2)->default(0);
        });

        // Preserve the tutor's existing three-term price and the legacy column.
        DB::table('packages')->update([
            'twelve_month_price' => DB::raw('nine_month_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['twelve_month_price', 'three_month_discount_percent',
                'six_month_discount_percent', 'twelve_month_discount_percent']);
        });
    }
};
