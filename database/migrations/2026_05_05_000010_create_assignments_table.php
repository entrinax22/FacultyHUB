<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->enum('type', ['essay', 'mcq', 'code']);
            $table->timestamp('due_date')->nullable();
            $table->decimal('max_score', 6, 2)->default(100);
            $table->decimal('passing_score', 6, 2)->nullable();
            $table->boolean('is_published')->default(false);
            $table->text('rubric')->nullable();
            $table->string('language', 30)->nullable();
            $table->timestamp('answer_release_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
