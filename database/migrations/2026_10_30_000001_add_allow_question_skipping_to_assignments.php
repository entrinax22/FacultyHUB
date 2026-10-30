<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('allow_question_skipping')->default(false);
        });

        DB::table('assignments')
            ->where('type', 'mcq')
            ->update(['allow_question_skipping' => true]);
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('allow_question_skipping');
        });
    }
};
