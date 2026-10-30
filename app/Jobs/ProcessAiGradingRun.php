<?php

namespace App\Jobs;

use App\Models\AiProcessingRun;
use App\Services\AIGraderService;
use App\Services\AiProcessingRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessAiGradingRun implements ShouldQueue
{
    use Queueable;

    // Unexpected exceptions are rescheduled transactionally by the coordinator.
    public int $tries = 1;

    public int $timeout = 120;

    public int $unexpectedAttempts = 0;

    public function __construct(public readonly int $runId, public readonly int $generation, int $unexpectedAttempts = 0)
    {
        $this->unexpectedAttempts = $unexpectedAttempts;
    }

    public function handle(AiProcessingRunService $processing, AIGraderService $ai): void
    {
        try {
            $processing->processQueuedGradingRun($this->runId, $this->generation, $ai);
        } catch (\Throwable $exception) {
            report($exception);
            $processing->rescheduleUnexpectedGradingFailure($this->runId, $this->generation, $this->unexpectedAttempts + 1);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::transaction(function () {
            $run = AiProcessingRun::query()->lockForUpdate()->find($this->runId);
            if ($run && $run->queue_generation === $this->generation && $run->isActive()) {
                $run->update(['queue_job_id' => null, 'error_code' => 'queue_failed']);
            }
        });
    }
}
