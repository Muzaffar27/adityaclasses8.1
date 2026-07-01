<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_access', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->default(3)->after('status');
            $table->decimal('requested_price', 10, 2)->default(0)->after('duration_months');
            $table->timestamp('expires_at')->nullable()->after('requested_price');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_access', function (Blueprint $table) {
            $table->dropColumn(['duration_months', 'requested_price', 'expires_at']);
        });
    }
};
