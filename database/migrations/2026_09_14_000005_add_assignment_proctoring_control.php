<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('proctoring_enabled')->default(false)->after('duration_minutes');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('proctoring_enabled');
        });
    }
};