<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'ai_processing_runs',
        'plagiarism_run_submissions',
        'plagiarism_run_pairs',
        'ai_processing_budget_windows',
        'ai_grading_run_submissions',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $engine = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )?->ENGINE;

            if (strtoupper((string) $engine) !== 'INNODB') {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'The AI processing tables must not be downgraded from InnoDB. Restore a backup if rollback is required.'
        );
    }
};
