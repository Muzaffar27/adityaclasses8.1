<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('academic_year', 20)->nullable();
            $table->string('student_phone', 32)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship', 80)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->timestamp('guardian_report_consent_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });

        DB::table('student_profiles')->insertUsing(
            ['user_id', 'created_at', 'updated_at'],
            DB::table('users')
                ->select([
                    'id',
                    DB::raw('CURRENT_TIMESTAMP as created_at'),
                    DB::raw('CURRENT_TIMESTAMP as updated_at'),
                ])
                ->where('role', 'student')
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
