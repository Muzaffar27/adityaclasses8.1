<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_corrections', function (Blueprint $table) {
            $table->string('question_id', 64)->primary();
            $table->string('status', 32)->default('queued');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('flagged_by');
            $table->timestamp('flagged_at');
            $table->json('original');
            $table->json('sources');
            $table->json('retry_sources')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('outcome', 64)->nullable();
            $table->string('replacement_id', 64)->nullable()->index();
            $table->json('corrected_content')->nullable();
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'flagged_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('question_corrections'); }
};
