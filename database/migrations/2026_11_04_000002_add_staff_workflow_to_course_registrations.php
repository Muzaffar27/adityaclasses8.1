<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_registrations', function (Blueprint $table) {
            $table->string('registration_status', 20)->default('new');
            $table->string('payment_status', 20)->default('unverified')->index();
            $table->text('staff_notes')->nullable();
            $table->unsignedInteger('workflow_version')->default(0);
            $table->foreignId('staff_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('staff_updated_at')->nullable();
            $table->foreignId('payment_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_verified_at')->nullable();
            $table->index(['registration_status', 'created_at'], 'course_registrations_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('course_registrations', function (Blueprint $table) {
            $table->dropForeign(['staff_updated_by']);
            $table->dropForeign(['payment_verified_by']);
            $table->dropIndex('course_registrations_status_created_index');
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['registration_status', 'payment_status', 'staff_notes', 'workflow_version',
                'staff_updated_by', 'staff_updated_at', 'payment_verified_by', 'payment_verified_at']);
        });
    }
};
