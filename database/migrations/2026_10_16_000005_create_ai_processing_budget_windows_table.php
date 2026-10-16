<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_processing_budget_windows', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('window_type', 10);
            $table->timestamp('window_starts_at');
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedBigInteger('estimated_input_tokens')->default(0);
            $table->timestamps();

            $table->unique(
                ['provider', 'model', 'window_type', 'window_starts_at'],
                'ai_processing_budget_window_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_processing_budget_windows');
    }
};
