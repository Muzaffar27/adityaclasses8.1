<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_learning_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->unsignedInteger('playback_seconds')->default(0);
            $table->unsignedInteger('question_pdf_seconds')->default(0);
            $table->timestamp('last_credited_at')->nullable();
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'activity_date']);
            $table->index(['activity_date', 'user_id']);
        });

        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->uuid('activity_session_id')->nullable();
            $table->timestamp('activity_observed_at')->nullable();
            $table->unsignedInteger('activity_position_seconds')->nullable();
            $table->boolean('activity_active')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropColumn(['activity_session_id', 'activity_observed_at', 'activity_position_seconds', 'activity_active']);
        });
        Schema::dropIfExists('student_learning_days');
    }
};
