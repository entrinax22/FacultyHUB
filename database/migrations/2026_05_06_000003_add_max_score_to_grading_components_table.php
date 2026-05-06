<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_components', function (Blueprint $table) {
            $table->decimal('max_score', 6, 2)->default(100)->after('weight_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('grading_components', function (Blueprint $table) {
            $table->dropColumn('max_score');
        });
    }
};

