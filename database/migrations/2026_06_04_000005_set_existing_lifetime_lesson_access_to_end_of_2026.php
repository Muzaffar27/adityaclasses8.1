<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_access')
            ->where('status', 'accepted')
            ->whereNull('expires_at')
            ->update([
                'expires_at' => '2026-12-31 23:59:59',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('lesson_access')
            ->where('status', 'accepted')
            ->where('expires_at', '2026-12-31 23:59:59')
            ->update([
                'expires_at' => null,
                'updated_at' => now(),
            ]);
    }
};
