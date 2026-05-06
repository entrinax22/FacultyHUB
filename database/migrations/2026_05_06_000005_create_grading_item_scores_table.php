<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_item_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_item_id')->constrained('grading_items')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->boolean('is_released')->default(false);
            $table->timestamps();

            $table->unique(['grading_item_id', 'student_id']);
            $table->index(['section_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_item_scores');
    }
};

