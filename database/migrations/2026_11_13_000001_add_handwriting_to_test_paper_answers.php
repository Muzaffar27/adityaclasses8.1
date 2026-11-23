<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('test_paper_answers', function (Blueprint $table) {
            $table->json('answer_ink')->nullable();
            $table->json('working_ink')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('test_paper_answers', fn (Blueprint $table) => $table->dropColumn(['answer_ink', 'working_ink']));
    }
};
