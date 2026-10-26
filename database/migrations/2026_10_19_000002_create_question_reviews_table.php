<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_reviews', function (Blueprint $table) {
            $table->string('id', 64)->primary(); // Content fingerprint: approval is per exact question version.
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('subject_id');
            $table->json('content');
            $table->json('origins');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('review_cycles')->default(0);
            $table->json('retry_sources')->nullable();
            $table->string('retry_outcome', 32)->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'grade_id', 'subject_id']);
        });
        Schema::table('lesson_question_sources', fn (Blueprint $table) => $table->boolean('force_reprocess')->default(false));
    }

    public function down(): void
    {
        Schema::table('lesson_question_sources', fn (Blueprint $table) => $table->dropColumn('force_reprocess'));
        Schema::dropIfExists('question_reviews');
    }
};
