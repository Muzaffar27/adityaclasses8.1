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
            $table->decimal('three_month_price', 10, 2)->default(0)->after('total_price');
            $table->decimal('six_month_price', 10, 2)->default(0)->after('three_month_price');
            $table->decimal('twelve_month_price', 10, 2)->default(0)->after('six_month_price');
        });

        DB::table('packages')->update([
            'three_month_price' => DB::raw('total_price * three_month_multiplier'),
            'six_month_price' => DB::raw('total_price * six_month_multiplier'),
            'twelve_month_price' => DB::raw('total_price * twelve_month_multiplier'),
        ]);

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'three_month_multiplier',
                'six_month_multiplier',
                'twelve_month_multiplier',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('three_month_multiplier', 6, 2)->default(1)->after('total_price');
            $table->decimal('six_month_multiplier', 6, 2)->default(2)->after('three_month_multiplier');
            $table->decimal('twelve_month_multiplier', 6, 2)->default(4)->after('six_month_multiplier');
        });

        DB::table('packages')->update([
            'three_month_multiplier' => DB::raw('CASE WHEN total_price > 0 THEN three_month_price / total_price ELSE 1 END'),
            'six_month_multiplier' => DB::raw('CASE WHEN total_price > 0 THEN six_month_price / total_price ELSE 2 END'),
            'twelve_month_multiplier' => DB::raw('CASE WHEN total_price > 0 THEN twelve_month_price / total_price ELSE 4 END'),
        ]);

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'three_month_price',
                'six_month_price',
                'twelve_month_price',
            ]);
        });
    }
};
