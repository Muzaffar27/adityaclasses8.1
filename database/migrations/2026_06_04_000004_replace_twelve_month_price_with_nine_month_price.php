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
            $table->decimal('nine_month_price', 10, 2)->default(0)->after('six_month_price');
        });

        DB::table('packages')->update([
            'nine_month_price' => DB::raw('twelve_month_price'),
        ]);

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('twelve_month_price');
        });

        DB::table('lesson_access')
            ->where('duration_months', 12)
            ->update(['duration_months' => 9]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('twelve_month_price', 10, 2)->default(0)->after('six_month_price');
        });

        DB::table('packages')->update([
            'twelve_month_price' => DB::raw('nine_month_price'),
        ]);

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('nine_month_price');
        });

        DB::table('lesson_access')
            ->where('duration_months', 9)
            ->update(['duration_months' => 12]);
    }
};
