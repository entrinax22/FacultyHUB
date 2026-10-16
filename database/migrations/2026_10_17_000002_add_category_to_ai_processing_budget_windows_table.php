<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_processing_budget_windows', function (Blueprint $table) {
            $table->string('category', 25)->default('plagiarism')->after('model');
            $table->dropUnique('ai_processing_budget_window_unique');
            $table->unique(
                ['provider', 'model', 'category', 'window_type', 'window_starts_at'],
                'ai_processing_budget_category_window_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_processing_budget_windows', function (Blueprint $table) {
            $table->dropUnique('ai_processing_budget_category_window_unique');
            $table->dropColumn('category');
            $table->unique(
                ['provider', 'model', 'window_type', 'window_starts_at'],
                'ai_processing_budget_window_unique'
            );
        });
    }
};
