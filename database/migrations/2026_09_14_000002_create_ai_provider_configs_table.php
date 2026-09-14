<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 50);
            $table->text('api_key')->nullable();
            $table->json('models')->nullable();
            $table->string('selected_model', 100);
            $table->boolean('enabled')->default(false);
            $table->string('status', 20)->default('inactive');
            $table->string('status_message')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamp('quota_exhausted_at')->nullable();
            $table->timestamps();

            $table->unique('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};
