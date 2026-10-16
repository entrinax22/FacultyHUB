<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_grading_run_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_processing_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('submission_id');
            $table->longText('content');
            $table->char('content_hash', 64);
            $table->string('status', 25)->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_eligible_at')->nullable();
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->json('result')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamps();

            $table->unique(['ai_processing_run_id', 'submission_id'], 'ai_grading_run_submission_unique');
            $table->index(['ai_processing_run_id', 'status', 'next_eligible_at'], 'ai_grading_run_submission_status_index');
            $table->index('lease_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_grading_run_submissions');
    }
};
