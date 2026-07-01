<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('three_month_multiplier', 6, 2)->default(1)->after('total_price');
            $table->decimal('six_month_multiplier', 6, 2)->default(2)->after('three_month_multiplier');
            $table->decimal('twelve_month_multiplier', 6, 2)->default(4)->after('six_month_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'three_month_multiplier',
                'six_month_multiplier',
                'twelve_month_multiplier',
            ]);
        });
    }
};
