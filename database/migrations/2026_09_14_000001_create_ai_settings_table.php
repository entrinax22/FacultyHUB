<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('provider', 50)->default('gemini');
            $table->text('api_key')->nullable();
            $table->string('model', 100)->default('gemini-2.0-flash');
            $table->decimal('temperature', 3, 2)->default(0.10);
            $table->unsignedInteger('max_output_tokens')->default(4096);
            $table->boolean('include_assignment_instructions')->default(true);
            $table->boolean('include_rubric')->default(true);
            $table->boolean('context_enabled')->default(true);
            $table->text('global_context')->nullable();
            $table->text('essay_context')->nullable();
            $table->text('code_context')->nullable();
            $table->text('plagiarism_context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
