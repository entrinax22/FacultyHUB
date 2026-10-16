<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_processing_runs', function (Blueprint $table) {
            $table->string('preparation_status', 25)->default('pending');
            $table->timestamp('preparation_started_at')->nullable();
            $table->timestamp('preparation_completed_at')->nullable();
            $table->timestamp('preparation_failed_at')->nullable();
            $table->index(['processing_type', 'preparation_status'], 'ai_processing_preparation_index');
        });
    }

    public function down(): void
    {
        Schema::table('ai_processing_runs', function (Blueprint $table) {
            $table->dropIndex('ai_processing_preparation_index');
            $table->dropColumn([
                'preparation_status',
                'preparation_started_at',
                'preparation_completed_at',
                'preparation_failed_at',
            ]);
        });
    }
};
