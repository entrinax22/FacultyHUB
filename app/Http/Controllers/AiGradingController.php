<?php

namespace App\Http\Controllers;

use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AIGraderService;
use App\Services\AiProcessingRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AiGradingController extends Controller
{
    public function start(Request $request, string $assignmentId, AiProcessingRunService $processing): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $validated = $request->validate([
            'mode' => 'required|in:individual,batch',
            'submission_id' => 'nullable|required_if:mode,individual|string',
        ]);

        if (! in_array($assignment->type, ['essay', 'code'], true)) {
            return $this->failure('Only essay and code assignments support AI grading.', 422);
        }

        $submission = isset($validated['submission_id'])
            ? $this->resolveSubmission($validated['submission_id'])
            : null;

        try {
            $run = $processing->prepareGradingRun($request->user(), $assignment, $submission);

            return $this->success('AI grading run is ready.', $processing->gradingRunStatus($run, $request->user()));
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return $this->failure('Unable to prepare the AI grading run.', 500);
        }
    }

    public function status(Request $request, string $assignmentId, AiProcessingRunService $processing): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run) {
            return $this->failure('No AI grading run was found.', 404);
        }

        return $this->success('AI grading status loaded successfully.', $processing->gradingRunStatus($run, $request->user()));
    }

    public function process(
        Request $request,
        string $assignmentId,
        AiProcessingRunService $processing,
        AIGraderService $ai
    ): JsonResponse {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run || ! $run->isActive()) {
            return $this->failure('This AI grading run is not available for processing.', 422);
        }

        try {
            $beforeCompleted = $run->completed_work_units;
            $beforeFailed = $run->failed_work_units;
            $run = $processing->processNextGradingSubmission($run, $request->user(), $ai);
            $outcome = match (true) {
                $run->completed_work_units > $beforeCompleted => 'completed',
                $run->failed_work_units > $beforeFailed || $run->status === AiProcessingRun::STATUS_FAILED => 'failed',
                in_array($run->error_code, ['provider_unavailable', 'provider_rate_limited', 'provider_request_failed', 'budget_exhausted', 'invalid_response'], true) => 'deferred',
                default => 'no_eligible_work',
            };
            $data = $processing->gradingRunStatus($run, $request->user()) + ['outcome' => $outcome];

            return response()->json([
                'success' => $outcome === 'completed',
                'message' => $this->outcomeMessage($outcome, $run->error_code),
                'data' => $data,
            ]);
        } catch (\RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'This AI grading run is already being processed.',
                'data' => $processing->gradingRunStatus($run->fresh(), $request->user()),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->failure('Unable to process the AI grading submission.', 500);
        }
    }

    public function retry(Request $request, string $assignmentId, AiProcessingRunService $processing): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run) {
            return $this->failure('No AI grading run was found.', 404);
        }

        try {
            $run = $processing->retryFailedGradingRun($run, $request->user());

            return $this->success('Failed AI grading work is ready to retry.', $processing->gradingRunStatus($run, $request->user()));
        } catch (\InvalidArgumentException) {
            return $this->failure('This AI grading run cannot be retried.', 422);
        }
    }

    public function cancel(Request $request, string $assignmentId, AiProcessingRunService $processing): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run) {
            return $this->failure('No AI grading run was found.', 404);
        }

        try {
            $run = $processing->cancelGradingRun($run, $request->user());

            return $this->success('AI grading run cancelled.', $processing->gradingRunStatus($run, $request->user()));
        } catch (\InvalidArgumentException) {
            return $this->failure('Only active AI grading runs can be cancelled.', 422);
        }
    }

    private function authorizedAssignment(Request $request, string $encryptedId): Assignment
    {
        $assignment = Assignment::query()->findOrFail($this->decryptId($encryptedId));

        abort_unless(
            $request->user()->isAdmin()
                || ($request->user()->isFaculty() && (int) $assignment->section?->faculty_id === (int) $request->user()->id),
            403
        );

        return $assignment;
    }

    private function resolveSubmission(string $encryptedId): Submission
    {
        return Submission::query()->findOrFail($this->decryptId($encryptedId));
    }

    private function processingRun(Assignment $assignment): ?AiProcessingRun
    {
        return AiProcessingRun::query()
            ->where('resource_type', Assignment::class)
            ->where('resource_id', $assignment->id)
            ->where('processing_type', AiProcessingRun::TYPE_GRADING)
            ->latest('id')
            ->first();
    }

    private function decryptId(string $encryptedId): int
    {
        try {
            return (int) Crypt::decryptString($encryptedId);
        } catch (\Throwable) {
            abort(404, 'Invalid resource identifier.');
        }
    }

    private function success(string $message, array $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $status);
    }

    private function outcomeMessage(string $outcome, ?string $errorCode): string
    {
        if ($outcome === 'completed') {
            return 'One AI grading submission was processed.';
        }

        if ($outcome === 'failed') {
            return 'AI grading failed after the allowed attempts or an integrity check.';
        }

        return match ($errorCode) {
            'provider_unavailable' => 'Gemini is temporarily unavailable. Grading was deferred.',
            'provider_rate_limited' => 'Gemini rate limits delayed grading. Try again after the scheduled time.',
            'budget_exhausted' => 'The configured AI grading budget is exhausted. No submission was processed.',
            'invalid_response' => 'Gemini returned an invalid grading response. Grading was deferred.',
            default => 'No eligible AI grading submission was processed.',
        };
    }
}
