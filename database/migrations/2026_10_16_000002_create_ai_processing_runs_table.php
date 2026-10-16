<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource_type', 100);
            $table->unsignedBigInteger('resource_id');
            $table->string('processing_type', 50);
            $table->string('status', 25)->default('pending');
            $table->unsignedInteger('total_work_units');
            $table->unsignedInteger('completed_work_units')->default(0);
            $table->unsignedInteger('failed_work_units')->default(0);
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retry_attempts')->default(3);
            $table->string('error_code', 100)->nullable();
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('active_key', 191)->nullable()->unique();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id', 'processing_type'], 'ai_processing_resource_index');
            $table->index(['owner_id', 'status'], 'ai_processing_owner_status_index');
            $table->index('lease_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_processing_runs');
    }
};
