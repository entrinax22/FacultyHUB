<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plagiarism_run_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_processing_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedInteger('ordinal');
            $table->longText('content');
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->unique(['ai_processing_run_id', 'submission_id'], 'plagiarism_run_submission_unique');
            $table->unique(['ai_processing_run_id', 'ordinal'], 'plagiarism_run_ordinal_unique');
            $table->index(['ai_processing_run_id', 'id'], 'plagiarism_run_submission_index');
        });

        Schema::create('plagiarism_run_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_processing_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_a_id')->constrained('plagiarism_run_submissions')->cascadeOnDelete();
            $table->foreignId('snapshot_b_id')->constrained('plagiarism_run_submissions')->cascadeOnDelete();
            $table->string('status', 25)->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_eligible_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->decimal('similarity_score', 5, 2)->nullable();
            $table->boolean('flagged')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['ai_processing_run_id', 'snapshot_a_id', 'snapshot_b_id'], 'plagiarism_run_pair_unique');
            $table->index(['ai_processing_run_id', 'status', 'next_eligible_at'], 'plagiarism_run_pair_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plagiarism_run_pairs');
        Schema::dropIfExists('plagiarism_run_submissions');
    }
};
