<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_processing_runs', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->index();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('plagiarism_published_run_id')
                ->nullable()
                ->constrained('ai_processing_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plagiarism_published_run_id');
        });

        Schema::table('ai_processing_runs', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
