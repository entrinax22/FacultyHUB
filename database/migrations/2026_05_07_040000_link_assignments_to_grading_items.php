<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('component_id')->nullable()->after('module_id')
                ->constrained('grading_components')->nullOnDelete();
        });

        Schema::table('grading_items', function (Blueprint $table) {
            $table->foreignId('assignment_id')->nullable()->after('section_id')
                ->constrained('assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grading_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_id');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('component_id');
        });
    }
};
