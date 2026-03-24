<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->string('topic');
            $table->string('title');
            $table->integer('part_number')->nullable();
            $table->text('description')->nullable();
            $table->text('vimeo_url');
            $table->string('duration')->nullable();
            $table->string('pdf_resource')->nullable();
            $table->boolean('is_active')->default(true); // To hide/show lessons
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
