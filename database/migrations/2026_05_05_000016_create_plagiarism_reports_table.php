<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plagiarism_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_a_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_b_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('similarity_score', 5, 2);
            $table->boolean('flagged')->default(false);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_a_id', 'student_b_id'], 'plagiarism_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plagiarism_reports');
    }
};
