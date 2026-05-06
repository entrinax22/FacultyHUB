<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sections — queried by faculty + semester constantly
        Schema::table('sections', function (Blueprint $table) {
            $table->index(['faculty_id', 'semester_id']);
            $table->index('semester_id');
        });

        // modules — filtered by section + published status on every student page load
        Schema::table('modules', function (Blueprint $table) {
            $table->index(['section_id', 'is_published']);
            $table->index(['section_id', 'order']);
        });

        // assignments — filtered by section + published + due_date frequently
        Schema::table('assignments', function (Blueprint $table) {
            $table->index(['section_id', 'is_published']);
            $table->index('due_date');
        });

        // submissions — looked up by student + status for dashboard notifications
        Schema::table('submissions', function (Blueprint $table) {
            $table->index(['student_id', 'status']);
        });

        // grades — released grades looked up by student constantly
        Schema::table('grades', function (Blueprint $table) {
            $table->index(['student_id', 'is_released']);
            $table->index(['student_id', 'section_id']);
            $table->index(['section_id', 'is_released']);
        });

        // enrollments — status filter used on every student auth check
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['student_id', 'status']);
            $table->index(['section_id', 'status']);
        });

        // users — role filter used in admin user listing
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex(['faculty_id', 'semester_id']);
            $table->dropIndex(['semester_id']);
        });
        Schema::table('modules', function (Blueprint $table) {
            $table->dropIndex(['section_id', 'is_published']);
            $table->dropIndex(['section_id', 'order']);
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['section_id', 'is_published']);
            $table->dropIndex(['due_date']);
        });
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'status']);
        });
        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'is_released']);
            $table->dropIndex(['student_id', 'section_id']);
            $table->dropIndex(['section_id', 'is_released']);
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'status']);
            $table->dropIndex(['section_id', 'status']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
