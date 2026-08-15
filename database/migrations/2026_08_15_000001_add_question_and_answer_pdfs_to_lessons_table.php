<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->renameColumn('pdf_resource', 'question_pdf_path');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->string('answer_pdf_path')->nullable()->after('question_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('answer_pdf_path');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->renameColumn('question_pdf_path', 'pdf_resource');
        });
    }
};
