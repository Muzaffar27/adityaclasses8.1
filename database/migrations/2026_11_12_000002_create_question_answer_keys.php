<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_answer_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('question_id', 64);
            $table->foreign('question_id')->references('id')->on('question_reviews')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('request_key', 64);
            $table->string('request_hash', 64);
            $table->string('question_content_hash', 64);
            $table->json('question_snapshot');
            $table->json('answer');
            $table->json('validation');
            $table->string('generator', 64);
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'version']);
            $table->unique(['question_id', 'request_key']);
            $table->index(['question_id', 'status', 'version']);
        });
    }

    public function down(): void { Schema::dropIfExists('question_answer_keys'); }
};
