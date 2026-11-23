<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->string('reference', 40)->unique();
            $table->char('payload_hash', 64);
            $table->json('details');
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_registrations');
    }
};
