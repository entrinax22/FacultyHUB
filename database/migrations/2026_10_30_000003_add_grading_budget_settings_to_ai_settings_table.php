<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->unsignedInteger('grading_daily_request_budget')
                ->default(max(1, (int) config('ai_processing.grading_daily_request_budget', 25)));
            $table->unsignedInteger('grading_daily_input_token_budget')
                ->default(max(1, (int) config('ai_processing.grading_daily_input_token_budget', 125000)));
            $table->unsignedInteger('grading_requests_per_minute')
                ->default(max(1, (int) config('ai_processing.grading_requests_per_minute', 2)));
            $table->boolean('grading_auto_continue')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'grading_daily_request_budget',
                'grading_daily_input_token_budget',
                'grading_requests_per_minute',
                'grading_auto_continue',
            ]);
        });
    }
};
