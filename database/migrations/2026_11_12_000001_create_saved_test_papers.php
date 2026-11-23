<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->uuid('batch_key');
            $table->string('purpose', 20);
            $table->json('topics');
            $table->timestamps();
            $table->unique(['student_id', 'grade_id', 'subject_id', 'purpose', 'batch_key'], 'assessment_batch_scope_unique');
        });
        Schema::create('test_papers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('assessment_batches')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->uuid('creation_key');
            $table->foreignUuid('previous_paper_id')->nullable()->constrained('test_papers')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('blueprint', 64);
            $table->string('status', 24)->default('generated');
            $table->string('grade_name');
            $table->string('subject_name');
            $table->boolean('marking_ready')->default(false);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'version']);
            $table->unique(['batch_id', 'creation_key']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('test_paper_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('paper_id')->constrained('test_papers')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('source_question_id', 64);
            $table->text('topic');
            $table->json('content');
            $table->json('provenance');
            $table->decimal('marks', 8, 2)->nullable();
            $table->json('marking_snapshot')->nullable();
            $table->unique(['paper_id', 'position']);
            $table->unique(['paper_id', 'source_question_id']);
            $table->index('source_question_id');
        });
        Schema::create('test_paper_assets', function (Blueprint $table) {
            $table->string('sha256', 64)->primary();
            // Private, content-addressed PNG copies survive deletion of original source files.
            $table->longText('base64_bytes');
        });
        Schema::create('test_paper_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('paper_id')->unique()->constrained('test_papers')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('test_paper_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('paper_id')->constrained('test_papers')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['paper_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_paper_events');
        Schema::dropIfExists('test_paper_attempts');
        Schema::dropIfExists('test_paper_assets');
        Schema::dropIfExists('test_paper_questions');
        Schema::dropIfExists('test_papers');
        Schema::dropIfExists('assessment_batches');
    }
};
