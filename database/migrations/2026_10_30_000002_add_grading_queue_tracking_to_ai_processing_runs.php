<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_processing_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('queue_generation')->default(0);
            $table->unsignedBigInteger('queue_job_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_processing_runs', function (Blueprint $table) {
            $table->dropColumn(['queue_generation', 'queue_job_id']);
        });
    }
};
