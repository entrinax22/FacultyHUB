<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('grading_components')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('max_score', 6, 2)->default(100);
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['section_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_items');
    }
};

