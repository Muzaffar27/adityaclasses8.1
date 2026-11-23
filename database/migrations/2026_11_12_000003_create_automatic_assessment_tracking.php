<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_assessment_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('enabled_by')->constrained('users')->restrictOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamp('activated_at');
            $table->json('baseline');
            $table->boolean('needs_reconcile')->default(false);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->json('issues')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'grade_id', 'subject_id'], 'assessment_enrolment_scope_unique');
            $table->index(['enabled', 'last_reconciled_at'], 'assessment_enrolment_rotation');
        });
        Schema::create('automatic_assessment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('enrolment_id')->constrained('student_assessment_enrolments')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->json('topics');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('details')->nullable();
            $table->foreignUuid('paper_id')->nullable()->constrained('test_papers')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['enrolment_id', 'sequence']);
            $table->unique('paper_id');
            $table->index(['status', 'updated_at']);
        });
        Schema::create('student_topic_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('enrolment_id')->constrained('student_assessment_enrolments')->restrictOnDelete();
            $table->string('topic_key', 64);
            $table->text('topic');
            $table->timestamp('completed_at');
            $table->json('evidence');
            $table->foreignUuid('automatic_batch_id')->nullable()->constrained('automatic_assessment_batches')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['enrolment_id', 'topic_key']);
            $table->index(['enrolment_id', 'automatic_batch_id', 'completed_at'], 'topic_completion_queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_topic_completions');
        Schema::dropIfExists('automatic_assessment_batches');
        Schema::dropIfExists('student_assessment_enrolments');
    }
};
