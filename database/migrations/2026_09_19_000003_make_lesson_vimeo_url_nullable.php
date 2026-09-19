<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->text('vimeo_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('lessons')->whereNull('vimeo_url')->update(['vimeo_url' => '']);

        Schema::table('lessons', function (Blueprint $table) {
            $table->text('vimeo_url')->nullable(false)->change();
        });
    }
};
