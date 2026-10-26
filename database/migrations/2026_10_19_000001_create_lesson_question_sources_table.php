<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_question_sources', function (Blueprint $table) {
            $table->id();
            // Keep processing history if a lesson is deleted; no cascading historical-data deletion.
            $table->unsignedBigInteger('lesson_id');
            $table->string('slot', 16);
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('topic');
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->string('status', 32)->default('pending');
            $table->string('source_sha256', 64)->nullable();
            $table->string('processor_version', 64)->nullable();
            $table->json('question_ids')->nullable();
            $table->unsignedInteger('question_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['lesson_id', 'slot']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['grade_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_question_sources');
    }
};
