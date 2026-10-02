<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesIsolatedLearningDatabase
{
    protected function prepareLearningDatabase(bool $withActivity = false): void
    {
        // A private connection: never migrate or reset the application's database.
        config(['database.default' => 'activity_testing', 'database.connections.activity_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2019_12_14_000001_create_personal_access_tokens_table.php',
            '2026_03_24_163241_create_grades_table.php',
            '2026_03_24_163313_create_subjects_table.php',
            '2026_03_24_163323_create_lessons_table.php',
            '2026_03_26_185603_create_lesson_access_table.php',
            '2026_06_04_000001_add_duration_price_and_expiry_to_lesson_access_table.php',
            '2026_09_21_000001_create_student_profiles_table.php',
            '2026_09_26_000002_create_lesson_progress_table.php',
            '2026_10_01_000001_add_video_source_to_lesson_progress_table.php',
        ] as $migration) {
            if ($migration === '2026_09_21_000001_create_student_profiles_table.php') {
                Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('student'));
            }
            (require database_path('migrations/' . $migration))->up();
        }
        Schema::table('lessons', function (Blueprint $table) {
            foreach (['answer_vimeo_url', 'lesson_pdf_path', 'question_pdf_path', 'question_pdf_2_path', 'answer_pdf_path'] as $field) {
                $table->string($field)->nullable();
            }
        });
        if ($withActivity) (require database_path('migrations/2026_10_02_000001_add_student_learning_days.php'))->up();
        DB::beginTransaction();
    }

    protected function closeLearningDatabase(): void
    {
        if (DB::connection('activity_testing')->transactionLevel() > 0) DB::connection('activity_testing')->rollBack();
        DB::purge('activity_testing');
    }
}
