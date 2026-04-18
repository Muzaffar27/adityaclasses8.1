<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('price', 10, 2)->default(0);

            $table->enum('type', ['subject', 'grade_subject', 'custom']);

            $table->timestamps();

            $table->index(['package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
