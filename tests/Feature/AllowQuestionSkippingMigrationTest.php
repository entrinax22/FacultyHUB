<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('migration backfills existing MCQ assignments and keeps the new column default false', function () {
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.question_skip_migration' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    DB::purge('question_skip_migration');
    DB::setDefaultConnection('question_skip_migration');

    try {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('type');
        });
        DB::table('assignments')->insert([
            ['id' => 1, 'type' => 'mcq'],
            ['id' => 2, 'type' => 'essay'],
        ]);

        $migration = require database_path('migrations/2026_10_30_000001_add_allow_question_skipping_to_assignments.php');
        $migration->up();

        expect((bool) DB::table('assignments')->where('id', 1)->value('allow_question_skipping'))->toBeTrue()
            ->and((bool) DB::table('assignments')->where('id', 2)->value('allow_question_skipping'))->toBeFalse();

        DB::table('assignments')->insert(['id' => 3, 'type' => 'mcq']);
        expect((bool) DB::table('assignments')->where('id', 3)->value('allow_question_skipping'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('question_skip_migration');
    }
});
