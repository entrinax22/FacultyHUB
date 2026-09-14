<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->nullable()->after('answer_release_at');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('submitted_at');
            $table->timestamp('expires_at')->nullable()->after('started_at');
        });

        Schema::create('proctoring_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_events');

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'expires_at']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};