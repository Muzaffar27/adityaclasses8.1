<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_paper_response_versions', function (Blueprint $table) {
            $table->foreignUuid('attempt_id')->primary()->constrained('test_paper_attempts')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('test_paper_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('attempt_id')->constrained('test_paper_attempts')->restrictOnDelete();
            $table->foreignId('question_id')->constrained('test_paper_questions')->restrictOnDelete();
            $table->text('answer');
            $table->text('working');
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('test_paper_answers'); Schema::dropIfExists('test_paper_response_versions');
    }
};
