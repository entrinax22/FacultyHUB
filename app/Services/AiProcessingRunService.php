<?php

namespace App\Services;

use App\Jobs\ProcessAiGradingRun;
use App\Models\AiFeedback;
use App\Models\AiGradingRunSubmission;
use App\Models\AiProcessingBudgetWindow;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\PlagiarismReport;
use App\Models\PlagiarismRunPair;
use App\Models\PlagiarismRunSubmission;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class AiProcessingRunService
{
    public function startQueuedGradingBatch(User $owner, Assignment $assignment): AiProcessingRun
    {
        $this->ensureGradingQueueConnection();

        return DB::transaction(function () use ($owner, $assignment) {
            Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (AiGradingRunSubmission::query()->where('status', AiGradingRunSubmission::STATUS_FAILED)
                ->where('error_code', 'outcome_unknown')
                ->whereHas('run', fn ($query) => $query->where('resource_type', Assignment::class)->where('resource_id', $assignment->id))
                ->exists()) {
                throw new \InvalidArgumentException('Review the unknown AI request outcome before starting another batch.');
            }
            $run = $this->startAssignmentRun($owner, $assignment, AiProcessingRun::TYPE_GRADING, 1);
            $created = $run->wasRecentlyCreated;
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->authorizeRun($owner, $run);

            if (! $created) {
                if ($run->queue_generation === 0) {
                    throw new \InvalidArgumentException('An individual grading run is already active.');
                }

                return $run;
            }

            $this->enqueueGradingRun($run);

            return $run->fresh();
        });
    }

    public function resumeQueuedGradingRun(AiProcessingRun $run, User $owner): AiProcessingRun
    {
        $this->ensureGradingQueueConnection();
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->queue_generation < 1 || ! $run->isActive()) {
                throw new \InvalidArgumentException('This batch cannot be resumed.');
            }

            if ($run->queue_job_id && DB::table(config('queue.connections.database.table', 'jobs'))->where('id', $run->queue_job_id)->exists()) {
                return $run;
            }

            if ($run->status === AiProcessingRun::STATUS_PROCESSING) {
                if (! $run->isLeaseExpired()) {
                    return $run;
                }
                $this->recoverExpiredGradingClaims($run, $run->queue_generation);
                $run->refresh();
                if (! $run->isActive()) {
                    return $run;
                }
            }

            if ($run->error_code === 'budget_exhausted') {
                $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_PENDING)
                    ->whereIn('error_code', ['budget_daily_requests', 'budget_daily_tokens'])
                    ->update(['next_eligible_at' => null]);
            }
            $run->error_code = null;
            $this->enqueueGradingRun($run);

            return $run->fresh();
        });
    }

    private function ensureGradingQueueConnection(): void
    {
        $connection = config('queue.connections.database.connection');
        if (config('queue.default') !== 'database' || ($connection && $connection !== DB::getDefaultConnection()) || config('queue.connections.database.after_commit')) {
            throw new \RuntimeException('AI batch grading requires the database queue on the application database connection.');
        }

        $workerTimeout = 120;
        $lease = (int) config('ai_processing.grading_queue_lease_seconds');
        if ((int) config('ai_processing.timeout_budget_seconds') >= $workerTimeout
            || $lease <= $workerTimeout
            || (int) config('queue.connections.database.retry_after') <= $lease) {
            throw new \RuntimeException('AI grading queue timeouts are not safely aligned.');
        }
    }

    private function enqueueGradingRun(AiProcessingRun $run, int $delay = 0): void
    {
        $generation = $run->queue_generation + 1;
        $job = new ProcessAiGradingRun($run->id, $generation);
        $jobId = Queue::connection('database')->laterOn('ai-grading', $delay, $job);
        $run->update(['queue_generation' => $generation, 'queue_job_id' => $jobId]);
    }

    public function processQueuedGradingRun(int $runId, int $generation, AIGraderService $ai): void
    {
        $this->ensureGradingQueueConnection();
        $run = DB::transaction(function () use ($runId, $generation) {
            $run = AiProcessingRun::query()->lockForUpdate()->find($runId);
            if (! $run || $run->queue_generation !== $generation || ! $run->isActive()) {
                return null;
            }
            if ($run->error_code === 'budget_waiting' && $run->gradingSubmissions()
                ->where('status', AiGradingRunSubmission::STATUS_PENDING)
                ->where('error_code', 'budget_minute_requests')->where('next_eligible_at', '>', now())->exists()) {
                return null;
            }

            $owner = $run->owner()->firstOrFail();
            $assignment = Assignment::query()->findOrFail($run->resource_id);
            $this->authorizeAssignment($owner, $assignment);
            if ($run->preparation_status !== AiProcessingRun::PREPARATION_PREPARED) {
                $run = $this->prepareGradingRun($owner, $assignment);
            }

            if (! $run->isActive()) {
                $run->update(['queue_job_id' => null]);

                return null;
            }

            return $run;
        });

        if (! $run) {
            return;
        }

        $run = $this->processNextGradingSubmission($run, $run->owner()->firstOrFail(), $ai, $generation);

        DB::transaction(function () use ($run, $generation) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->queue_generation !== $generation) {
                return;
            }
            if (! $run->isActive() || $run->error_code === 'budget_exhausted') {
                $run->update(['queue_job_id' => null]);

                return;
            }
            if ($run->error_code === 'budget_waiting') {
                $reset = $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_PENDING)
                    ->where('error_code', 'budget_minute_requests')->min('next_eligible_at');
                if ($reset) {
                    $this->enqueueGradingRun($run, max(1, (int) ceil(now()->diffInSeconds(Carbon::parse($reset), false))));

                    return;
                }
            }
            if ($run->status === AiProcessingRun::STATUS_PROCESSING) {
                $delay = $run->lease_expires_at
                    ? max(1, (int) ceil(now()->diffInSeconds($run->lease_expires_at, false)) + 1)
                    : 30;
                $this->enqueueGradingRun($run, $delay);

                return;
            }

            $next = $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_PENDING)->min('next_eligible_at');
            $ready = $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_PENDING)
                ->where(fn ($query) => $query->whereNull('next_eligible_at')->orWhere('next_eligible_at', '<=', now()))->exists();
            $delay = ! $ready && $next ? max(1, (int) ceil(now()->diffInSeconds(Carbon::parse($next), false))) : 0;
            $this->enqueueGradingRun($run, $delay);
        });
    }

    public function startAssignmentRun(
        User $owner,
        Assignment $assignment,
        string $processingType,
        int $totalWorkUnits
    ): AiProcessingRun {
        $this->authorizeAssignment($owner, $assignment);
        $this->validateProcessingType($processingType);

        if ($totalWorkUnits < 0 || ($totalWorkUnits < 1 && $processingType !== AiProcessingRun::TYPE_PLAGIARISM)) {
            throw new \InvalidArgumentException('Processing runs require at least one work unit.');
        }

        $activeKey = $this->activeKey($assignment, $processingType);

        try {
            return DB::transaction(function () use ($owner, $assignment, $processingType, $totalWorkUnits, $activeKey) {
                $existing = AiProcessingRun::query()
                    ->where('active_key', $activeKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                if ($processingType === AiProcessingRun::TYPE_GRADING
                    && AiGradingRunSubmission::query()->where('status', AiGradingRunSubmission::STATUS_FAILED)
                        ->where('error_code', 'outcome_unknown')
                        ->whereHas('run', fn ($query) => $query->where('resource_type', Assignment::class)->where('resource_id', $assignment->id))
                        ->exists()) {
                    throw new \InvalidArgumentException('Review the unknown AI request outcome before starting another grading run.');
                }

                return AiProcessingRun::create([
                    'owner_id' => $owner->id,
                    'resource_type' => Assignment::class,
                    'resource_id' => $assignment->id,
                    'processing_type' => $processingType,
                    'status' => AiProcessingRun::STATUS_PENDING,
                    'total_work_units' => $totalWorkUnits,
                    'max_retry_attempts' => $this->maxRetryAttempts(),
                    'active_key' => $activeKey,
                    'preparation_status' => AiProcessingRun::PREPARATION_PENDING,
                ]);
            });
        } catch (QueryException $exception) {
            $existing = AiProcessingRun::query()
                ->where('active_key', $activeKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function claim(AiProcessingRun $run, User $owner, ?int $generation = null): ?string
    {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $generation) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($generation !== null && $run->queue_generation !== $generation) {
                return null;
            }

            if ($run->status === AiProcessingRun::STATUS_PROCESSING && $run->isLeaseExpired()) {
                $run->update([
                    'status' => AiProcessingRun::STATUS_PENDING,
                    'retry_count' => min(255, $run->retry_count + 1),
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);

                if ($run->processing_type === AiProcessingRun::TYPE_PLAGIARISM) {
                    PlagiarismRunPair::query()
                        ->where('ai_processing_run_id', $run->id)
                        ->where('status', PlagiarismRunPair::STATUS_PROCESSING)
                        ->update([
                            'status' => PlagiarismRunPair::STATUS_PENDING,
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($run->status !== AiProcessingRun::STATUS_PENDING) {
                return null;
            }

            if (
                $run->processing_type === AiProcessingRun::TYPE_PLAGIARISM
                && $run->preparation_status !== AiProcessingRun::PREPARATION_PREPARED
            ) {
                return null;
            }

            if ($run->retry_count >= $run->max_retry_attempts) {
                $this->failRun($run, 'retry_limit_reached');
                $run->save();

                return null;
            }

            $token = Str::random(64);

            $run->update([
                'status' => AiProcessingRun::STATUS_PROCESSING,
                'started_at' => $run->started_at ?? now(),
                'lease_token' => $token,
                'lease_expires_at' => now()->addSeconds($this->leaseSeconds($run)),
                'error_code' => null,
            ]);

            return $token;
        });
    }

    public function recordProgress(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        int $completedWorkUnits
    ): AiProcessingRun {
        if ($completedWorkUnits < 1) {
            throw new \InvalidArgumentException('Completed work units must be positive.');
        }

        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $completedWorkUnits) {
            $run = $this->lockedLease($run, $leaseToken);
            $run->completed_work_units = min(
                $run->total_work_units,
                $run->completed_work_units + $completedWorkUnits
            );

            $this->finishOrRelease($run);

            return tap($run)->save();
        });
    }

    public function recordFailure(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        string $errorCode,
        int $failedWorkUnits = 1
    ): AiProcessingRun {
        if ($failedWorkUnits < 1) {
            throw new \InvalidArgumentException('Failed work units must be positive.');
        }

        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $errorCode, $failedWorkUnits) {
            $run = $this->lockedLease($run, $leaseToken);
            $run->failed_work_units = min(
                $run->total_work_units - $run->completed_work_units,
                $run->failed_work_units + $failedWorkUnits
            );
            $run->error_code = $this->safeErrorCode($errorCode);
            $run->retry_count = min(255, $run->retry_count + 1);

            if ($run->retry_count >= $run->max_retry_attempts) {
                $this->failRun($run, $run->error_code);
            } else {
                $this->finishOrRelease($run);
            }

            return tap($run)->save();
        });
    }

    public function recoverExpiredRuns(): int
    {
        $recovered = 0;

        AiProcessingRun::query()
            ->where('status', AiProcessingRun::STATUS_PROCESSING)
            ->where('lease_expires_at', '<=', now())
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (AiProcessingRun $run) use (&$recovered) {
                $updated = DB::transaction(function () use ($run) {
                    $run = AiProcessingRun::query()->lockForUpdate()->find($run->id);

                    if (! $run || $run->status !== AiProcessingRun::STATUS_PROCESSING || ! $run->isLeaseExpired()) {
                        return false;
                    }

                    if ($run->processing_type === AiProcessingRun::TYPE_GRADING && $run->queue_generation > 0) {
                        $this->recoverExpiredGradingClaims($run, $run->queue_generation);
                        $run->refresh();
                        if (! $run->isActive()) {
                            return true;
                        }
                    }

                    if ($run->retry_count >= $run->max_retry_attempts) {
                        $this->failRun($run, 'retry_limit_reached');
                        $run->save();
                    } else {
                        $run->update([
                            'status' => AiProcessingRun::STATUS_PENDING,
                            'retry_count' => min(255, $run->retry_count + 1),
                            'lease_token' => null,
                            'lease_expires_at' => null,
                        ]);

                        if ($run->processing_type === AiProcessingRun::TYPE_PLAGIARISM) {
                            PlagiarismRunPair::query()
                                ->where('ai_processing_run_id', $run->id)
                                ->where('status', PlagiarismRunPair::STATUS_PROCESSING)
                                ->update([
                                    'status' => PlagiarismRunPair::STATUS_PENDING,
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    return true;
                });

                $recovered += $updated ? 1 : 0;
            });

        return $recovered;
    }

    public function limits(): array
    {
        return [
            'work_units_per_request' => max(1, (int) config('ai_processing.work_units_per_request')),
            'max_input_bytes' => max(1, (int) config('ai_processing.max_input_bytes')),
            'max_retry_attempts' => $this->maxRetryAttempts(),
            'timeout_budget_seconds' => max(1, (int) config('ai_processing.timeout_budget_seconds')),
        ];
    }

    public function preparePlagiarismRun(User $owner, Assignment $assignment): AiProcessingRun
    {
        $run = $this->startAssignmentRun($owner, $assignment, AiProcessingRun::TYPE_PLAGIARISM, 0);

        if ($run->preparation_status === AiProcessingRun::PREPARATION_PREPARED) {
            return $run;
        }

        try {
            $this->snapshotEligibleSubmissions($run, $assignment);
            $this->generatePlagiarismPairs($run);

            return $this->completePlagiarismPreparation($run);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($run) {
                $lockedRun = AiProcessingRun::query()->lockForUpdate()->find($run->id);

                if (! $lockedRun || $lockedRun->preparation_status === AiProcessingRun::PREPARATION_PREPARED) {
                    return;
                }

                $lockedRun->update([
                    'status' => AiProcessingRun::STATUS_FAILED,
                    'error_code' => 'preparation_failed',
                    'failed_at' => now(),
                    'active_key' => null,
                    'preparation_status' => AiProcessingRun::PREPARATION_FAILED,
                    'preparation_failed_at' => now(),
                ]);
            });

            throw $exception;
        }
    }

    public function prepareGradingRun(
        User $owner,
        Assignment $assignment,
        ?Submission $submission = null
    ): AiProcessingRun {
        $this->authorizeAssignment($owner, $assignment);

        if ($submission && (int) $submission->assignment_id !== (int) $assignment->id) {
            abort(404);
        }

        $run = $this->startAssignmentRun($owner, $assignment, AiProcessingRun::TYPE_GRADING, 1);

        if ($submission && $run->queue_generation > 0) {
            throw new \InvalidArgumentException('A batch grading run is already active.');
        }

        $eligible = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->whereIn('status', ['pending', 'grading'])
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->whereDoesntHave('grade')
            ->whereDoesntHave('aiFeedback')
            ->when($submission, fn ($query) => $query->whereKey($submission->id));

        $eligible->orderBy('id')->chunkById(100, function ($submissions) use ($run) {
            $snapshots = $submissions->map(fn (Submission $item) => [
                'ai_processing_run_id' => $run->id,
                'submission_id' => $item->id,
                'content' => $item->content,
                'content_hash' => hash('sha256', $item->content),
                'status' => AiGradingRunSubmission::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            AiGradingRunSubmission::query()->insertOrIgnore($snapshots);
        });

        return DB::transaction(function () use ($run) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);
            $run->update([
                'total_work_units' => $run->gradingSubmissions()->count(),
                'preparation_status' => AiProcessingRun::PREPARATION_PREPARED,
                'preparation_started_at' => $run->preparation_started_at ?? now(),
                'preparation_completed_at' => now(),
            ]);

            if ($run->total_work_units === 0) {
                $run->update([
                    'status' => AiProcessingRun::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'active_key' => null,
                ]);
            }

            return $run->fresh();
        });
    }

    public function claimNextGradingSubmission(AiProcessingRun $run, User $owner, ?int $generation = null): ?AiGradingRunSubmission
    {
        $this->authorizeRun($owner, $run);
        $this->recoverExpiredGradingClaims($run, $generation);
        $leaseToken = $this->claim($run, $owner, $generation);

        if (! $leaseToken) {
            return null;
        }

        return DB::transaction(function () use ($run, $leaseToken) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $work = AiGradingRunSubmission::query()
                ->where('ai_processing_run_id', $lockedRun->id)
                ->where('status', AiGradingRunSubmission::STATUS_PENDING)
                ->where(function ($query) {
                    $query->whereNull('next_eligible_at')->orWhere('next_eligible_at', '<=', now());
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $work) {
                $lockedRun->update([
                    'status' => AiProcessingRun::STATUS_PENDING,
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);

                return null;
            }

            $work->update([
                'status' => AiGradingRunSubmission::STATUS_PROCESSING,
                'lease_token' => $leaseToken,
                'lease_expires_at' => $lockedRun->lease_expires_at,
            ]);

            return $work->fresh();
        });
    }

    public function recoverExpiredGradingClaims(AiProcessingRun $run, ?int $generation = null): void
    {
        if ($run->processing_type !== AiProcessingRun::TYPE_GRADING) {
            return;
        }

        DB::transaction(function () use ($run, $generation) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($generation !== null && $run->queue_generation !== $generation) {
                return;
            }
            $expired = AiGradingRunSubmission::query()
                ->where('ai_processing_run_id', $run->id)
                ->where('status', AiGradingRunSubmission::STATUS_PROCESSING)
                ->where('lease_expires_at', '<=', now())
                ->lockForUpdate()->get();
            $unknown = $run->queue_generation > 0 && $expired->contains('error_code', 'provider_in_flight');
            foreach ($expired as $work) {
                $isUnknown = $run->queue_generation > 0 && $work->error_code === 'provider_in_flight';
                $work->update([
                    'status' => $isUnknown ? AiGradingRunSubmission::STATUS_FAILED : AiGradingRunSubmission::STATUS_PENDING,
                    'error_code' => $isUnknown ? 'outcome_unknown' : $work->error_code,
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);
            }
            if ($unknown) {
                $this->syncGradingRunProgress($run, 'outcome_unknown');
                $this->failRun($run, 'outcome_unknown');
                $run->queue_job_id = null;
                $run->save();
            }
        });
    }

    public function processNextGradingSubmission(
        AiProcessingRun $run,
        User $owner,
        AIGraderService $ai,
        ?int $generation = null
    ): AiProcessingRun {
        if ($generation === null && $run->error_code === 'budget_exhausted') {
            $this->authorizeRun($owner, $run);
            $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_PENDING)
                ->whereIn('error_code', ['budget_minute_requests', 'budget_daily_requests', 'budget_daily_tokens'])
                ->update(['next_eligible_at' => null]);
        }
        $work = $this->claimNextGradingSubmission($run, $owner, $generation);

        if (! $work) {
            return $run->fresh();
        }

        $leaseToken = (string) $work->lease_token;
        $assignment = Assignment::query()->findOrFail($run->resource_id);
        if ($generation !== null) {
            $protected = $this->checkGradingWorkBeforeProvider($run, $owner, $leaseToken, $work);
            if ($protected) {
                return $protected;
            }
        }
        $estimatedTokens = max(1, (int) ceil((
            strlen($work->content)
            + strlen((string) $assignment->title)
            + strlen($ai->toPlainText($assignment->instructions))
            + strlen($ai->toPlainText($assignment->rubric))
        ) / 4));

        $budget = $this->reserveGradingBudget($ai->modelUsed(), $estimatedTokens);
        if ($budget !== null) {
            return $this->releaseGradingSubmissionForBudget($run, $owner, $leaseToken, $work, $budget);
        }

        $this->markGradingAttempt($run, $owner, $leaseToken, $work);

        $result = $assignment->type === 'code'
            ? $ai->gradeCode(
                $assignment->title,
                $ai->toPlainText($assignment->instructions),
                $ai->toPlainText($assignment->rubric),
                $work->content,
                (string) $assignment->language,
                (float) $assignment->max_score,
                true
            )
            : $ai->gradeEssay(
                $assignment->title,
                $ai->toPlainText($assignment->instructions),
                $ai->toPlainText($assignment->rubric),
                $work->content,
                (float) $assignment->max_score,
                true
            );

        if (isset($result['error'])) {
            $errorCode = $this->safeErrorCode((string) ($result['error_code'] ?? 'provider_request_failed'));

            if ($generation !== null && in_array($errorCode, ['provider_unavailable', 'provider_request_failed'], true)) {
                $errorCode = 'outcome_unknown';
            }

            return $this->recordGradingFailure(
                $run,
                $owner,
                $leaseToken,
                $work,
                $errorCode,
                $generation !== null ? $errorCode === 'provider_rate_limited' : in_array($errorCode, ['provider_rate_limited', 'provider_unavailable', 'provider_request_failed'], true)
            );
        }

        try {
            $validated = $ai->validateGradeResponse($result, (float) $assignment->max_score, $assignment->type);
        } catch (\InvalidArgumentException) {
            return $this->recordGradingFailure($run, $owner, $leaseToken, $work, 'invalid_response', $generation === null);
        }

        return $this->completeGradingSubmission($run, $owner, $leaseToken, $work, $validated, $ai->modelUsed());
    }

    public function cancelGradingRun(AiProcessingRun $run, User $owner): AiProcessingRun
    {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->processing_type !== AiProcessingRun::TYPE_GRADING || ! $run->isActive()) {
                throw new \InvalidArgumentException('Only active grading runs can be cancelled.');
            }

            $unknown = $run->queue_generation > 0 && $run->gradingSubmissions()
                ->where('status', AiGradingRunSubmission::STATUS_PROCESSING)
                ->where('error_code', 'provider_in_flight')->exists();
            if ($unknown) {
                $run->gradingSubmissions()
                    ->where('status', AiGradingRunSubmission::STATUS_PROCESSING)
                    ->where('error_code', 'provider_in_flight')
                    ->update([
                        'status' => AiGradingRunSubmission::STATUS_FAILED,
                        'error_code' => 'outcome_unknown',
                        'lease_token' => null,
                        'lease_expires_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $run->gradingSubmissions()
                ->whereIn('status', [AiGradingRunSubmission::STATUS_PENDING, AiGradingRunSubmission::STATUS_PROCESSING])
                ->update([
                    'status' => AiGradingRunSubmission::STATUS_FAILED,
                    'error_code' => 'cancelled',
                    'next_eligible_at' => null,
                    'lease_token' => null,
                    'lease_expires_at' => null,
                    'updated_at' => now(),
                ]);
            if ($run->queue_generation > 0) {
                $run->queue_generation++;
                $run->queue_job_id = null;
            }
            $this->syncGradingRunProgress($run, $unknown ? 'outcome_unknown' : 'cancelled');
            $this->failRun($run, $unknown ? 'outcome_unknown' : 'cancelled');
            $run->save();

            return $run->fresh();
        });
    }

    public function retryFailedGradingRun(AiProcessingRun $run, User $owner): AiProcessingRun
    {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

            if (
                $run->processing_type !== AiProcessingRun::TYPE_GRADING
                || $run->status !== AiProcessingRun::STATUS_FAILED
                || $run->retry_count >= $run->max_retry_attempts
            ) {
                throw new \InvalidArgumentException('This grading run cannot be retried.');
            }

            $retryable = $run->gradingSubmissions()
                ->where('status', AiGradingRunSubmission::STATUS_FAILED)
                ->whereIn('error_code', [
                    'provider_rate_limited',
                    'provider_unavailable',
                    'provider_request_failed',
                    'invalid_response',
                    'outcome_unknown',
                ]);

            if (! (clone $retryable)->exists()) {
                throw new \InvalidArgumentException('This grading run has no recoverable failures.');
            }

            $activeKey = $this->activeKey(Assignment::query()->findOrFail($run->resource_id), AiProcessingRun::TYPE_GRADING);
            $conflict = AiProcessingRun::query()
                ->where('active_key', $activeKey)
                ->whereKeyNot($run->id)
                ->exists();

            if ($conflict) {
                throw new \InvalidArgumentException('Another grading run is already active.');
            }

            $retryable->update([
                'status' => AiGradingRunSubmission::STATUS_PENDING,
                'attempt_count' => 0,
                'next_eligible_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'updated_at' => now(),
            ]);
            $run->update([
                'status' => AiProcessingRun::STATUS_PENDING,
                'failed_work_units' => $run->gradingSubmissions()
                    ->where('status', AiGradingRunSubmission::STATUS_FAILED)
                    ->count(),
                'retry_count' => $run->retry_count + 1,
                'error_code' => null,
                'failed_at' => null,
                'active_key' => $activeKey,
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);

            return $run->fresh();
        });
    }

    public function gradingRunStatus(AiProcessingRun $run, User $owner): array
    {
        $this->authorizeRun($owner, $run);

        if ($run->processing_type !== AiProcessingRun::TYPE_GRADING) {
            throw new \InvalidArgumentException('This is not an AI grading run.');
        }

        $counts = $run->gradingSubmissions()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $pending = (int) ($counts[AiGradingRunSubmission::STATUS_PENDING] ?? 0);
        $processing = (int) ($counts[AiGradingRunSubmission::STATUS_PROCESSING] ?? 0);
        $completed = (int) ($counts[AiGradingRunSubmission::STATUS_COMPLETED] ?? 0);
        $failed = (int) ($counts[AiGradingRunSubmission::STATUS_FAILED] ?? 0);
        $nextEligibleAt = $run->gradingSubmissions()
            ->where('status', AiGradingRunSubmission::STATUS_PENDING)
            ->whereNotNull('next_eligible_at')
            ->min('next_eligible_at');
        $hasReadyWork = $run->gradingSubmissions()
            ->where('status', AiGradingRunSubmission::STATUS_PENDING)
            ->where(fn ($query) => $query->whereNull('next_eligible_at')->orWhere('next_eligible_at', '<=', now()))
            ->exists();
        $hasRetryableFailure = $run->gradingSubmissions()
            ->where('status', AiGradingRunSubmission::STATUS_FAILED)
            ->whereIn('error_code', [
                'provider_rate_limited',
                'provider_unavailable',
                'provider_request_failed',
                'invalid_response',
                'outcome_unknown',
            ])
            ->exists();

        $unknown = $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_FAILED)
            ->where('error_code', 'outcome_unknown')->count();
        $queued = $run->queue_generation > 0 && $run->queue_job_id !== null
            && DB::table(config('queue.connections.database.table', 'jobs'))->where('id', $run->queue_job_id)->exists();
        $preparing = $run->queue_generation > 0 && $run->preparation_status !== AiProcessingRun::PREPARATION_PREPARED;

        $budgetWork = $run->gradingSubmissions()->where('status', AiGradingRunSubmission::STATUS_PENDING)
            ->whereIn('error_code', ['budget_minute_requests', 'budget_daily_requests', 'budget_daily_tokens'])
            ->first(['error_code', 'next_eligible_at']);

        return [
            'status' => $run->status,
            'preparation_status' => $run->preparation_status,
            'is_queued_batch' => $run->queue_generation > 0,
            'queued' => $queued,
            'can_resume' => $run->queue_generation > 0 && $run->isActive() && ! $queued
                && ($run->status !== AiProcessingRun::STATUS_PROCESSING || $run->isLeaseExpired()),
            'stalled' => $run->queue_generation > 0 && $run->status === AiProcessingRun::STATUS_PROCESSING
                && (! $queued || $run->isLeaseExpired()),
            'outcome_unknown_count' => $unknown,
            'total_submissions' => $preparing ? 0 : $run->total_work_units,
            'pending_count' => $pending,
            'processing_count' => $processing,
            'completed_count' => $completed,
            'failed_count' => $failed,
            'progress_percentage' => $preparing ? 0 : ($run->total_work_units > 0
                ? round(($completed / $run->total_work_units) * 100, 2)
                : 100),
            'next_eligible_at' => $nextEligibleAt
                ? Carbon::parse($nextEligibleAt)->toIso8601String()
                : null,
            'budget_reason' => $budgetWork?->error_code,
            'budget_reset_at' => $budgetWork?->next_eligible_at?->toIso8601String(),
            'can_continue' => $run->queue_generation === 0 && $run->status === AiProcessingRun::STATUS_PENDING
                && ($hasReadyWork || $run->error_code === 'budget_exhausted'),
            'can_retry' => $run->status === AiProcessingRun::STATUS_FAILED
                && $run->retry_count < $run->max_retry_attempts
                && $hasRetryableFailure,
            'can_cancel' => $run->isActive(),
            'error_code' => $run->error_code,
            'error_message' => match ($run->error_code) {
                'provider_unavailable' => 'Gemini is temporarily unavailable.',
                'provider_rate_limited' => 'Gemini rate limits or quota delayed processing.',
                'provider_request_failed' => 'The AI provider request could not be completed.',
                'invalid_response' => 'Gemini returned an invalid grading response.',
                'budget_exhausted' => 'The configured AI grading budget is exhausted.',
                'budget_waiting' => 'Waiting for the per-minute AI grading request limit to reset.',
                'cancelled' => 'This AI grading run was cancelled.',
                'submission_changed' => 'The submission changed after processing started.',
                'submission_deleted' => 'The submission is no longer available.',
                'grade_protected' => 'A faculty decision protects this submission from AI changes.',
                'outcome_unknown' => 'The provider outcome is unknown. Review the submission before retrying.',
                'queue_failed' => 'The grading worker stopped. Resume the batch when ready.',
                default => null,
            },
        ];
    }

    public function processNextPlagiarismPair(
        AiProcessingRun $run,
        User $owner,
        AIGraderService $ai
    ): AiProcessingRun {
        $leaseToken = $this->claim($run, $owner);

        if (! $leaseToken) {
            return $run->fresh();
        }

        $pair = $this->claimNextPlagiarismPair($run, $owner, $leaseToken);

        if (! $pair) {
            return $run->fresh();
        }

        $assignment = Assignment::query()->findOrFail($run->resource_id);

        try {
            $estimatedTokens = $ai->estimatePlagiarismPairInputTokens(
                $pair->snapshotA,
                $pair->snapshotB,
                $assignment->title
            );
        } catch (\InvalidArgumentException) {
            return $this->recordPlagiarismPairFailure($run, $owner, $leaseToken, $pair, 'input_too_large', false);
        }

        if (! $this->reservePlagiarismBudget($ai->modelUsed(), $estimatedTokens)) {
            return $this->releasePlagiarismPairForBudget($run, $owner, $leaseToken, $pair);
        }

        $this->markPlagiarismPairAttempt($run, $owner, $leaseToken, $pair);
        $result = $ai->analyzePlagiarismPair($pair->snapshotA, $pair->snapshotB, $assignment->title);

        if (isset($result['error'])) {
            $errorCode = $this->safeErrorCode((string) ($result['error_code'] ?? 'provider_request_failed'));

            return $this->recordPlagiarismPairFailure(
                $run,
                $owner,
                $leaseToken,
                $pair,
                $errorCode,
                in_array($errorCode, ['provider_rate_limited', 'provider_unavailable', 'provider_request_failed'], true)
            );
        }

        try {
            $validated = $ai->validatePlagiarismPairResponse($result);
        } catch (\InvalidArgumentException) {
            return $this->recordPlagiarismPairFailure($run, $owner, $leaseToken, $pair, 'invalid_response', false);
        }

        return $this->completePlagiarismPair($run, $owner, $leaseToken, $pair, $validated);
    }

    public function cancelPlagiarismRun(AiProcessingRun $run, User $owner): AiProcessingRun
    {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

            if (
                $run->processing_type !== AiProcessingRun::TYPE_PLAGIARISM
                || ! $run->isActive()
            ) {
                throw new \InvalidArgumentException('Only active plagiarism runs can be cancelled.');
            }

            $run->update([
                'status' => AiProcessingRun::STATUS_FAILED,
                'error_code' => 'cancelled',
                'failed_at' => now(),
                'active_key' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);

            return $run;
        });
    }

    public function publishPlagiarismRun(
        User $owner,
        Assignment $assignment,
        AiProcessingRun $run,
        AIGraderService $ai
    ): AiProcessingRun {
        $this->authorizeAssignment($owner, $assignment);
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($assignment, $run, $ai) {
            $assignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

            if ((int) $assignment->plagiarism_published_run_id === (int) $run->id) {
                return $run;
            }

            if (
                ((int) $assignment->plagiarism_published_run_id > (int) $run->id)
                || $run->published_at
                || $run->resource_type !== Assignment::class
                || (int) $run->resource_id !== (int) $assignment->id
                || $run->processing_type !== AiProcessingRun::TYPE_PLAGIARISM
                || $run->status !== AiProcessingRun::STATUS_COMPLETED
                || $run->preparation_status !== AiProcessingRun::PREPARATION_PREPARED
                || $run->lease_token
                || $run->lease_expires_at
                || $run->failed_work_units > 0
                || $run->total_work_units < 1
            ) {
                throw new \InvalidArgumentException('This plagiarism run cannot be published.');
            }

            $this->validatePublicationSnapshots($assignment, $run);
            $this->validatePublicationPairs($run, $ai);

            PlagiarismReport::query()
                ->where('assignment_id', $assignment->id)
                ->delete();

            $run->plagiarismPairs()
                ->where('status', PlagiarismRunPair::STATUS_COMPLETED)
                ->with(['snapshotA:id,student_id', 'snapshotB:id,student_id'])
                ->orderBy('id')
                ->chunkById(100, function ($pairs) use ($assignment) {
                    $timestamp = now();
                    $reports = $pairs->map(fn (PlagiarismRunPair $pair) => [
                        'assignment_id' => $assignment->id,
                        'student_a_id' => $pair->snapshotA->student_id,
                        'student_b_id' => $pair->snapshotB->student_id,
                        'similarity_score' => $pair->similarity_score,
                        'flagged' => $pair->flagged,
                        'explanation' => $pair->explanation,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all();

                    DB::table('plagiarism_reports')->insert($reports);
                });

            $assignment->update(['plagiarism_published_run_id' => $run->id]);
            $run->update(['published_at' => now()]);

            return $run;
        });
    }

    private function lockedLease(AiProcessingRun $run, string $leaseToken): AiProcessingRun
    {
        $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

        if (
            $run->status !== AiProcessingRun::STATUS_PROCESSING
            || ! $run->lease_token
            || ! hash_equals($run->lease_token, $leaseToken)
            || $run->isLeaseExpired()
        ) {
            throw new \RuntimeException('AI processing lease is no longer valid.');
        }

        return $run;
    }

    private function claimNextPlagiarismPair(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken
    ): ?PlagiarismRunPair {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $pair = PlagiarismRunPair::query()
                ->where('ai_processing_run_id', $lockedRun->id)
                ->where('status', PlagiarismRunPair::STATUS_PENDING)
                ->where(function ($query) {
                    $query->whereNull('next_eligible_at')->orWhere('next_eligible_at', '<=', now());
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $pair) {
                $this->releaseLease($lockedRun);
                $lockedRun->save();

                return null;
            }

            if ($pair->attempt_count >= $this->maxPairAttempts()) {
                $pair->update([
                    'status' => PlagiarismRunPair::STATUS_FAILED,
                    'error_code' => 'retry_limit_reached',
                ]);
                $lockedRun->failed_work_units = min(
                    $lockedRun->total_work_units - $lockedRun->completed_work_units,
                    $lockedRun->failed_work_units + 1
                );
                $lockedRun->error_code = 'retry_limit_reached';
                $this->finishOrRelease($lockedRun);
                $lockedRun->save();

                return null;
            }

            $pair->update(['status' => PlagiarismRunPair::STATUS_PROCESSING]);

            return $pair->fresh(['snapshotA', 'snapshotB']);
        });
    }

    private function markPlagiarismPairAttempt(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        PlagiarismRunPair $pair
    ): void {
        $this->authorizeRun($owner, $run);

        DB::transaction(function () use ($run, $leaseToken, $pair) {
            $this->lockedLease($run, $leaseToken);
            $pair = PlagiarismRunPair::query()->lockForUpdate()->findOrFail($pair->id);

            if ($pair->status !== PlagiarismRunPair::STATUS_PROCESSING) {
                throw new \RuntimeException('Plagiarism pair is no longer claimed.');
            }

            $pair->increment('attempt_count');
        });
    }

    private function completePlagiarismPair(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        PlagiarismRunPair $pair,
        array $result
    ): AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $pair, $result) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $pair = PlagiarismRunPair::query()->lockForUpdate()->findOrFail($pair->id);

            if ($pair->status === PlagiarismRunPair::STATUS_COMPLETED) {
                return $lockedRun;
            }

            if ($pair->status !== PlagiarismRunPair::STATUS_PROCESSING) {
                throw new \RuntimeException('Plagiarism pair is no longer claimed.');
            }

            $pair->update([
                'status' => PlagiarismRunPair::STATUS_COMPLETED,
                'similarity_score' => $result['similarity'],
                'flagged' => $result['flagged'],
                'explanation' => $result['explanation'],
                'error_code' => null,
                'next_eligible_at' => null,
            ]);
            $lockedRun->completed_work_units = min(
                $lockedRun->total_work_units,
                $lockedRun->completed_work_units + 1
            );
            $this->finishOrRelease($lockedRun);
            $lockedRun->save();

            return $lockedRun;
        });
    }

    private function recordPlagiarismPairFailure(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        PlagiarismRunPair $pair,
        string $errorCode,
        bool $isTransient
    ): AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $pair, $errorCode, $isTransient) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $pair = PlagiarismRunPair::query()->lockForUpdate()->findOrFail($pair->id);
            $errorCode = $this->safeErrorCode($errorCode);

            if ($isTransient && $pair->attempt_count < $this->maxPairAttempts()) {
                $pair->update([
                    'status' => PlagiarismRunPair::STATUS_PENDING,
                    'error_code' => $errorCode,
                    'next_eligible_at' => now()->addSeconds(
                        $this->retryBackoffSeconds() * (2 ** max(0, $pair->attempt_count - 1))
                    ),
                ]);
                $lockedRun->error_code = $errorCode;
                $this->releaseLease($lockedRun);
                $lockedRun->save();

                return $lockedRun;
            }

            $pair->update([
                'status' => PlagiarismRunPair::STATUS_FAILED,
                'error_code' => $errorCode,
                'next_eligible_at' => null,
            ]);
            $lockedRun->failed_work_units = min(
                $lockedRun->total_work_units - $lockedRun->completed_work_units,
                $lockedRun->failed_work_units + 1
            );
            $lockedRun->error_code = $errorCode;
            $this->finishOrRelease($lockedRun);
            $lockedRun->save();

            return $lockedRun;
        });
    }

    private function releasePlagiarismPairForBudget(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        PlagiarismRunPair $pair
    ): AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $pair) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $pair = PlagiarismRunPair::query()->lockForUpdate()->findOrFail($pair->id);

            if ($pair->status === PlagiarismRunPair::STATUS_PROCESSING) {
                $pair->update(['status' => PlagiarismRunPair::STATUS_PENDING]);
            }

            $lockedRun->error_code = 'budget_exhausted';
            $this->releaseLease($lockedRun);
            $lockedRun->save();

            return $lockedRun;
        });
    }

    private function checkGradingWorkBeforeProvider(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        AiGradingRunSubmission $work
    ): ?AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $work) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $work = AiGradingRunSubmission::query()->lockForUpdate()->findOrFail($work->id);
            if ($work->status !== AiGradingRunSubmission::STATUS_PROCESSING
                || ! $work->lease_token || ! hash_equals($work->lease_token, $leaseToken)) {
                throw new \RuntimeException('AI grading work lease is no longer valid.');
            }
            $submission = Submission::query()->lockForUpdate()->find($work->submission_id);
            if (! $submission) {
                return $this->failGradingIntegrityCheck($lockedRun, $work, 'submission_deleted');
            }
            if (! hash_equals($work->content_hash, hash('sha256', (string) $submission->content))) {
                return $this->failGradingIntegrityCheck($lockedRun, $work, 'submission_changed');
            }
            if ($submission->status === 'approved' || $submission->grade()->exists() || $submission->aiFeedback()->exists()) {
                return $this->failGradingIntegrityCheck($lockedRun, $work, 'grade_protected');
            }

            return null;
        });
    }

    private function markGradingAttempt(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        AiGradingRunSubmission $work
    ): void {
        $this->authorizeRun($owner, $run);

        DB::transaction(function () use ($run, $leaseToken, $work) {
            $this->lockedLease($run, $leaseToken);
            $work = AiGradingRunSubmission::query()->lockForUpdate()->findOrFail($work->id);

            if (
                $work->status !== AiGradingRunSubmission::STATUS_PROCESSING
                || ! $work->lease_token
                || ! hash_equals($work->lease_token, $leaseToken)
            ) {
                throw new \RuntimeException('AI grading work lease is no longer valid.');
            }

            $work->update([
                'attempt_count' => $work->attempt_count + 1,
                'error_code' => $run->queue_generation > 0 ? 'provider_in_flight' : null,
            ]);
        });
    }

    private function completeGradingSubmission(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        AiGradingRunSubmission $work,
        array $result,
        string $model
    ): AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $work, $result, $model) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $work = AiGradingRunSubmission::query()->lockForUpdate()->findOrFail($work->id);

            if ($work->status === AiGradingRunSubmission::STATUS_COMPLETED) {
                return $lockedRun;
            }

            if (
                $work->status !== AiGradingRunSubmission::STATUS_PROCESSING
                || ! $work->lease_token
                || ! hash_equals($work->lease_token, $leaseToken)
            ) {
                throw new \RuntimeException('AI grading work lease is no longer valid.');
            }

            $submission = Submission::query()->lockForUpdate()->find($work->submission_id);

            if (! $submission) {
                return $this->failGradingIntegrityCheck($lockedRun, $work, 'submission_deleted');
            }

            if (! hash_equals($work->content_hash, hash('sha256', (string) $submission->content))) {
                return $this->failGradingIntegrityCheck($lockedRun, $work, 'submission_changed');
            }

            if (
                $submission->status === 'approved'
                || $submission->grade()->exists()
                || $submission->aiFeedback()->exists()
            ) {
                return $this->failGradingIntegrityCheck($lockedRun, $work, 'grade_protected');
            }

            AiFeedback::create([
                'submission_id' => $submission->id,
                'score' => $result['score'],
                'feedback_json' => $result,
                'generated_at' => now(),
                'model_used' => $model,
            ]);
            $submission->update(['status' => 'graded']);
            $work->update([
                'status' => AiGradingRunSubmission::STATUS_COMPLETED,
                'result' => $result,
                'error_code' => null,
                'next_eligible_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            $this->syncGradingRunProgress($lockedRun);

            return $lockedRun;
        });
    }

    private function recordGradingFailure(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        AiGradingRunSubmission $work,
        string $errorCode,
        bool $retryable
    ): AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $work, $errorCode, $retryable) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $work = AiGradingRunSubmission::query()->lockForUpdate()->findOrFail($work->id);
            $errorCode = $this->safeErrorCode($errorCode);

            if ($retryable && $work->attempt_count < $this->maxRetryAttempts()) {
                $work->update([
                    'status' => AiGradingRunSubmission::STATUS_PENDING,
                    'error_code' => $errorCode,
                    'next_eligible_at' => now()->addSeconds(
                        $this->gradingRetryBackoffSeconds() * (2 ** max(0, $work->attempt_count - 1))
                    ),
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);
                $lockedRun->error_code = $errorCode;
                $this->releaseLease($lockedRun);
                $lockedRun->save();

                return $lockedRun;
            }

            $work->update([
                'status' => AiGradingRunSubmission::STATUS_FAILED,
                'error_code' => $errorCode,
                'next_eligible_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            $this->syncGradingRunProgress($lockedRun, $errorCode);

            if ($errorCode === 'outcome_unknown' && $lockedRun->queue_generation > 0) {
                $this->failRun($lockedRun, 'outcome_unknown');
                $lockedRun->queue_job_id = null;
                $lockedRun->save();
            }

            return $lockedRun;
        });
    }

    private function releaseGradingSubmissionForBudget(
        AiProcessingRun $run,
        User $owner,
        string $leaseToken,
        AiGradingRunSubmission $work,
        array $budget
    ): AiProcessingRun {
        $this->authorizeRun($owner, $run);

        return DB::transaction(function () use ($run, $leaseToken, $work, $budget) {
            $lockedRun = $this->lockedLease($run, $leaseToken);
            $work = AiGradingRunSubmission::query()->lockForUpdate()->findOrFail($work->id);
            $work->update([
                'status' => AiGradingRunSubmission::STATUS_PENDING,
                'error_code' => $budget['reason'],
                'next_eligible_at' => $budget['reset_at'],
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            $lockedRun->error_code = $budget['reason'] === 'budget_minute_requests' && $lockedRun->queue_generation > 0
                ? 'budget_waiting' : 'budget_exhausted';
            $this->releaseLease($lockedRun);
            $lockedRun->save();

            return $lockedRun;
        });
    }

    private function failGradingIntegrityCheck(
        AiProcessingRun $run,
        AiGradingRunSubmission $work,
        string $errorCode
    ): AiProcessingRun {
        $work->update([
            'status' => AiGradingRunSubmission::STATUS_FAILED,
            'error_code' => $errorCode,
            'next_eligible_at' => null,
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
        $this->syncGradingRunProgress($run, $errorCode);

        return $run;
    }

    private function syncGradingRunProgress(AiProcessingRun $run, ?string $errorCode = null): void
    {
        $run->completed_work_units = $run->gradingSubmissions()
            ->where('status', AiGradingRunSubmission::STATUS_COMPLETED)
            ->count();
        $run->failed_work_units = $run->gradingSubmissions()
            ->where('status', AiGradingRunSubmission::STATUS_FAILED)
            ->count();
        $run->error_code = $errorCode;
        $this->finishOrRelease($run);
        $run->save();
    }

    private function reservePlagiarismBudget(string $model, int $estimatedInputTokens): bool
    {
        return $this->reserveBudget('plagiarism', $model, $estimatedInputTokens) === null;
    }

    private function reserveGradingBudget(string $model, int $estimatedInputTokens): ?array
    {
        return $this->reserveBudget('grading', $model, $estimatedInputTokens);
    }

    private function reserveBudget(string $category, string $model, int $estimatedInputTokens): ?array
    {
        $timezone = (string) config("ai_processing.{$category}_budget_timezone");
        $now = now($timezone);
        $daily = $this->budgetWindow($category, 'daily', $model, $now->copy()->startOfDay()->utc());
        $minute = $this->budgetWindow($category, 'minute', $model, $now->copy()->startOfMinute()->utc());

        return DB::transaction(function () use ($category, $daily, $minute, $estimatedInputTokens, $now) {
            $daily = $this->lockedBudgetWindow($daily);
            $minute = $this->lockedBudgetWindow($minute);

            if ($daily->request_count + 1 > max(1, (int) config("ai_processing.{$category}_daily_request_budget"))) {
                return ['reason' => 'budget_daily_requests', 'reset_at' => $now->copy()->addDay()->startOfDay()->utc()];
            }
            if ($daily->estimated_input_tokens + $estimatedInputTokens > max(1, (int) config("ai_processing.{$category}_daily_input_token_budget"))) {
                return ['reason' => 'budget_daily_tokens', 'reset_at' => $now->copy()->addDay()->startOfDay()->utc()];
            }
            if ($minute->request_count + 1 > max(1, (int) config("ai_processing.{$category}_requests_per_minute"))) {
                return ['reason' => 'budget_minute_requests', 'reset_at' => $now->copy()->addMinute()->startOfMinute()->utc()];
            }

            $daily->increment('request_count');
            $daily->increment('estimated_input_tokens', $estimatedInputTokens);
            $minute->increment('request_count');
            $minute->increment('estimated_input_tokens', $estimatedInputTokens);

            return null;
        });
    }

    private function budgetWindow(
        string $category,
        string $windowType,
        string $model,
        \DateTimeInterface $startsAt
    ): AiProcessingBudgetWindow {
        try {
            return AiProcessingBudgetWindow::query()->firstOrCreate([
                'provider' => 'gemini',
                'model' => $model,
                'category' => $category,
                'window_type' => $windowType,
                'window_starts_at' => $startsAt,
            ]);
        } catch (QueryException) {
            return AiProcessingBudgetWindow::query()
                ->where([
                    'provider' => 'gemini',
                    'model' => $model,
                    'category' => $category,
                    'window_type' => $windowType,
                    'window_starts_at' => $startsAt,
                ])
                ->firstOrFail();
        }
    }

    private function lockedBudgetWindow(AiProcessingBudgetWindow $window): AiProcessingBudgetWindow
    {
        return AiProcessingBudgetWindow::query()->lockForUpdate()->findOrFail($window->id);
    }

    private function releaseLease(AiProcessingRun $run): void
    {
        $run->status = AiProcessingRun::STATUS_PENDING;
        $run->lease_token = null;
        $run->lease_expires_at = null;
    }

    private function snapshotEligibleSubmissions(AiProcessingRun $run, Assignment $assignment): void
    {
        DB::transaction(function () use ($run, $assignment) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->plagiarismSubmissions()->exists()) {
                $run->update(['preparation_status' => AiProcessingRun::PREPARATION_PAIRING]);

                return;
            }

            $run->update([
                'preparation_status' => AiProcessingRun::PREPARATION_SNAPSHOTTING,
                'preparation_started_at' => $run->preparation_started_at ?? now(),
            ]);

            $eligibleSubmissions = $assignment->submissions()
                ->whereNotNull('content')
                ->where('content', '!=', '');
            $maximumSubmissionId = (clone $eligibleSubmissions)->max('id');
            $ordinal = 0;
            $maximumSubmissions = max(1, (int) config('ai_processing.plagiarism_max_submissions_per_run'));

            if ($maximumSubmissionId) {
                $eligibleSubmissions
                    ->where('id', '<=', $maximumSubmissionId)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->chunkById(100, function ($submissions) use ($run, &$ordinal, $maximumSubmissions) {
                        foreach ($submissions as $submission) {
                            $ordinal++;

                            if ($ordinal > $maximumSubmissions) {
                                throw new \LengthException('Plagiarism run exceeds the configured submission limit.');
                            }

                            PlagiarismRunSubmission::create([
                                'ai_processing_run_id' => $run->id,
                                'submission_id' => $submission->id,
                                'student_id' => $submission->student_id,
                                'ordinal' => $ordinal,
                                'content' => $submission->content,
                                'content_hash' => hash('sha256', $submission->content),
                            ]);
                        }
                    });
            }

            $run->update(['preparation_status' => AiProcessingRun::PREPARATION_PAIRING]);
        });
    }

    private function validatePublicationSnapshots(Assignment $assignment, AiProcessingRun $run): void
    {
        $snapshotCount = $run->plagiarismSubmissions()->count();
        $eligibleCount = $assignment->submissions()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->count();

        if ($snapshotCount !== $eligibleCount) {
            throw new \InvalidArgumentException('Submission changes require a new plagiarism run.');
        }

        $run->plagiarismSubmissions()
            ->orderBy('id')
            ->chunkById(100, function ($snapshots) use ($assignment) {
                $submissions = Submission::query()
                    ->where('assignment_id', $assignment->id)
                    ->whereIn('id', $snapshots->pluck('submission_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($submissions->count() !== $snapshots->count()) {
                    throw new \InvalidArgumentException('Submission changes require a new plagiarism run.');
                }

                foreach ($snapshots as $snapshot) {
                    $submission = $submissions->get($snapshot->submission_id);

                    if (
                        ! $submission
                        || (int) $submission->student_id !== (int) $snapshot->student_id
                        || ! hash_equals($snapshot->content_hash, hash('sha256', $submission->content))
                    ) {
                        throw new \InvalidArgumentException('Submission changes require a new plagiarism run.');
                    }
                }
            });
    }

    private function validatePublicationPairs(AiProcessingRun $run, AIGraderService $ai): void
    {
        $snapshotCount = $run->plagiarismSubmissions()->count();
        $expectedPairs = intdiv($snapshotCount * ($snapshotCount - 1), 2);
        $pairCount = $run->plagiarismPairs()->count();
        $completedPairs = $run->plagiarismPairs()
            ->where('status', PlagiarismRunPair::STATUS_COMPLETED)
            ->count();

        if (
            $expectedPairs !== $run->total_work_units
            || $pairCount !== $expectedPairs
            || $completedPairs !== $expectedPairs
        ) {
            throw new \InvalidArgumentException('This plagiarism run has incomplete comparisons.');
        }

        $run->plagiarismPairs()
            ->where('status', PlagiarismRunPair::STATUS_COMPLETED)
            ->orderBy('id')
            ->chunkById(100, function ($pairs) use ($ai) {
                foreach ($pairs as $pair) {
                    $ai->validatePlagiarismPairResponse([
                        'similarity' => $pair->similarity_score,
                        'flagged' => $pair->flagged,
                        'explanation' => $pair->explanation,
                    ]);
                }
            });
    }

    private function generatePlagiarismPairs(AiProcessingRun $run): void
    {
        PlagiarismRunSubmission::query()
            ->where('ai_processing_run_id', $run->id)
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (PlagiarismRunSubmission $first) use ($run) {
                PlagiarismRunSubmission::query()
                    ->where('ai_processing_run_id', $run->id)
                    ->where('ordinal', '>', $first->ordinal)
                    ->orderBy('id')
                    ->chunkById(100, function ($secondSnapshots) use ($run, $first) {
                        $pairs = $secondSnapshots->map(fn (PlagiarismRunSubmission $second) => [
                            'ai_processing_run_id' => $run->id,
                            'snapshot_a_id' => $first->id,
                            'snapshot_b_id' => $second->id,
                            'status' => PlagiarismRunPair::STATUS_PENDING,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->all();

                        PlagiarismRunPair::query()->insertOrIgnore($pairs);
                    });
            });
    }

    private function completePlagiarismPreparation(AiProcessingRun $run): AiProcessingRun
    {
        return DB::transaction(function () use ($run) {
            $run = AiProcessingRun::query()->lockForUpdate()->findOrFail($run->id);
            $submissionCount = $run->plagiarismSubmissions()->count();
            $expectedPairs = intdiv($submissionCount * ($submissionCount - 1), 2);
            $actualPairs = $run->plagiarismPairs()->count();

            if ($actualPairs !== $expectedPairs) {
                $run->update(['preparation_status' => AiProcessingRun::PREPARATION_PAIRING]);

                return $run;
            }

            $attributes = [
                'total_work_units' => $expectedPairs,
                'preparation_status' => AiProcessingRun::PREPARATION_PREPARED,
                'preparation_completed_at' => now(),
            ];

            if ($expectedPairs === 0) {
                $attributes += [
                    'status' => AiProcessingRun::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'active_key' => null,
                ];
            }

            $run->update($attributes);

            return $run;
        });
    }

    private function finishOrRelease(AiProcessingRun $run): void
    {
        if ($run->completed_work_units + $run->failed_work_units >= $run->total_work_units) {
            if ($run->failed_work_units > 0) {
                $this->failRun($run, $run->error_code ?? 'processing_failed');
            } else {
                $run->status = AiProcessingRun::STATUS_COMPLETED;
                $run->completed_at = now();
                $run->active_key = null;
                $run->lease_token = null;
                $run->lease_expires_at = null;
                if ($run->queue_generation > 0) {
                    $run->queue_job_id = null;
                }
            }

            return;
        }

        $run->status = AiProcessingRun::STATUS_PENDING;
        $run->lease_token = null;
        $run->lease_expires_at = null;
    }

    private function failRun(AiProcessingRun $run, string $errorCode): void
    {
        $run->status = AiProcessingRun::STATUS_FAILED;
        $run->error_code = $this->safeErrorCode($errorCode);
        $run->failed_at = now();
        $run->active_key = null;
        $run->lease_token = null;
        $run->lease_expires_at = null;
        if ($run->queue_generation > 0) {
            $run->queue_job_id = null;
        }
    }

    private function authorizeAssignment(User $owner, Assignment $assignment): void
    {
        abort_unless(
            $owner->isAdmin()
                || ($owner->isFaculty() && (int) $assignment->section?->faculty_id === (int) $owner->id),
            403
        );
    }

    private function authorizeRun(User $owner, AiProcessingRun $run): void
    {
        abort_unless(
            $owner->isAdmin() || ($owner->isFaculty() && (int) $run->owner_id === (int) $owner->id),
            403
        );
    }

    private function validateProcessingType(string $processingType): void
    {
        if (! in_array($processingType, [AiProcessingRun::TYPE_GRADING, AiProcessingRun::TYPE_PLAGIARISM], true)) {
            throw new \InvalidArgumentException('Unsupported AI processing type.');
        }
    }

    private function activeKey(Assignment $assignment, string $processingType): string
    {
        return implode(':', [Assignment::class, $assignment->id, $processingType]);
    }

    private function maxRetryAttempts(): int
    {
        return max(1, min(255, (int) config('ai_processing.max_retry_attempts')));
    }

    private function leaseSeconds(AiProcessingRun $run): int
    {
        return max(1, (int) config(
            $run->processing_type === AiProcessingRun::TYPE_GRADING && $run->queue_generation > 0
                ? 'ai_processing.grading_queue_lease_seconds'
                : 'ai_processing.lease_seconds'
        ));
    }

    private function maxPairAttempts(): int
    {
        return max(1, min(255, (int) config('ai_processing.plagiarism_max_pair_attempts')));
    }

    private function retryBackoffSeconds(): int
    {
        return max(1, (int) config('ai_processing.plagiarism_retry_backoff_seconds'));
    }

    private function gradingRetryBackoffSeconds(): int
    {
        return max(1, (int) config('ai_processing.grading_retry_backoff_seconds'));
    }

    private function safeErrorCode(string $errorCode): string
    {
        return preg_match('/^[a-z0-9_]{1,100}$/', $errorCode)
            ? $errorCode
            : 'processing_failed';
    }
}
