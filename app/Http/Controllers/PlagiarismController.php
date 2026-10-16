<?php

namespace App\Http\Controllers;

use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Services\AIGraderService;
use App\Services\AiProcessingRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class PlagiarismController extends Controller
{
    /**
     * Render the plagiarism page.
     */
    public function show(Request $request, string $assignmentId): Response
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);

        return Inertia::render('assignments/PlagiarismReport', [
            'assignmentId' => $this->encryptId($assignment->id),
        ]);
    }

    /**
     * Load plagiarism data for Axios.
     */
    public function data(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);

        $reports = $assignment->plagiarismReports()
            ->with(['studentA', 'studentB'])
            ->orderByDesc('similarity_score')
            ->get();

        $assignment->load('section.subject');

        return response()->json([
            'success' => true,
            'message' => 'Plagiarism reports loaded successfully.',
            'data' => [
                'assignment' => $this->transformAssignment($assignment),

                'reports' => $reports
                    ->map(fn ($report) => [
                        'id' => $this->encryptId($report->id),

                        'assignment_id' => $report->assignment_id
                            ? $this->encryptId($report->assignment_id)
                            : null,

                        'student_a' => $report->studentA
                            ? $this->transformStudent($report->studentA)
                            : null,

                        'student_b' => $report->studentB
                            ? $this->transformStudent($report->studentB)
                            : null,

                        'similarity_score' => $report->similarity_score,
                        'flagged' => $report->flagged,
                        'explanation' => $report->explanation,
                        'created_at' => $report->created_at,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function start(Request $request, string $assignmentId, AiProcessingRunService $processing): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $eligibleSubmissions = $assignment->submissions()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->count();

        if ($eligibleSubmissions > max(1, (int) config('ai_processing.plagiarism_max_submissions_per_run'))) {
            return response()->json([
                'success' => false,
                'message' => 'This assignment exceeds the configured plagiarism processing limit.',
            ], 422);
        }

        try {
            $run = $processing->preparePlagiarismRun($request->user(), $assignment);

            return response()->json([
                'success' => true,
                'message' => 'Plagiarism processing run is ready.',
                'data' => $this->transformProcessingRun($run),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to prepare the plagiarism processing run.',
            ], 500);
        }
    }

    public function status(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run) {
            return response()->json([
                'success' => false,
                'message' => 'No plagiarism processing run was found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plagiarism processing status loaded successfully.',
            'data' => $this->transformProcessingRun($run),
        ]);
    }

    public function process(
        Request $request,
        string $assignmentId,
        AiProcessingRunService $processing,
        AIGraderService $ai
    ): JsonResponse {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run || ! $run->isActive() || $run->preparation_status !== AiProcessingRun::PREPARATION_PREPARED) {
            return response()->json([
                'success' => false,
                'message' => 'This plagiarism run is not available for processing.',
            ], 422);
        }

        try {
            $completedPairs = $run->completed_work_units;
            $run = $processing->processNextPlagiarismPair($run, $request->user(), $ai);
            $comparisonCompleted = $run->completed_work_units > $completedPairs;
            $outcome = $comparisonCompleted
                ? 'completed'
                : ($run->status === AiProcessingRun::STATUS_FAILED || $run->failed_work_units > 0 ? 'failed' : 'deferred');

            return response()->json([
                'success' => $comparisonCompleted,
                'message' => $comparisonCompleted
                    ? 'One plagiarism comparison was processed.'
                    : $this->processingOutcomeMessage($run, $outcome),
                'data' => [
                    ...$this->transformProcessingRun($run),
                    'outcome' => $outcome,
                ],
            ]);
        } catch (\RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'This plagiarism run is already being processed.',
                'data' => $this->transformProcessingRun($run->fresh()),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process the plagiarism comparison.',
            ], 500);
        }
    }

    public function retry(
        Request $request,
        string $assignmentId,
        AiProcessingRunService $processing,
        AIGraderService $ai
    ): JsonResponse {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $processing->recoverExpiredRuns();
        $run = $this->processingRun($assignment);

        if (
            ! $run
            || $run->status !== AiProcessingRun::STATUS_PENDING
            || $run->preparation_status !== AiProcessingRun::PREPARATION_PREPARED
            || $run->failed_work_units > 0
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This plagiarism run cannot be retried.',
            ], 422);
        }

        return $this->process($request, $assignmentId, $processing, $ai);
    }

    public function cancel(Request $request, string $assignmentId, AiProcessingRunService $processing): JsonResponse
    {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run) {
            return response()->json([
                'success' => false,
                'message' => 'No plagiarism processing run was found.',
            ], 404);
        }

        try {
            $run = $processing->cancelPlagiarismRun($run, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Plagiarism processing run cancelled.',
                'data' => $this->transformProcessingRun($run),
            ]);
        } catch (\InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'message' => 'Only active plagiarism runs can be cancelled.',
            ], 422);
        }
    }

    public function publish(
        Request $request,
        string $assignmentId,
        AiProcessingRunService $processing,
        AIGraderService $ai
    ): JsonResponse {
        $assignment = $this->authorizedAssignment($request, $assignmentId);
        $run = $this->processingRun($assignment);

        if (! $run) {
            return response()->json([
                'success' => false,
                'message' => 'No plagiarism processing run was found.',
            ], 404);
        }

        try {
            $run = $processing->publishPlagiarismRun($request->user(), $assignment, $run, $ai);

            return response()->json([
                'success' => true,
                'message' => 'Plagiarism reports published successfully.',
                'data' => $this->transformProcessingRun($run),
            ]);
        } catch (\InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'message' => 'This plagiarism run is not eligible for publication.',
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to publish plagiarism reports.',
            ], 500);
        }
    }

    /**
     * Resolve an assignment from an encrypted ID.
     */
    private function resolveAssignment(string $encryptedId): Assignment
    {
        $id = $this->decryptId($encryptedId);

        return Assignment::query()
            ->findOrFail($id);
    }

    private function authorizedAssignment(Request $request, string $encryptedId): Assignment
    {
        $assignment = $this->resolveAssignment($encryptedId);

        abort_unless(
            ! $request->user()->isFaculty()
            || (int) $assignment->section?->faculty_id === (int) $request->user()->id,
            403
        );

        return $assignment;
    }

    private function processingRun(Assignment $assignment): ?AiProcessingRun
    {
        return AiProcessingRun::query()
            ->where('resource_type', Assignment::class)
            ->where('resource_id', $assignment->id)
            ->where('processing_type', AiProcessingRun::TYPE_PLAGIARISM)
            ->latest('id')
            ->first();
    }

    private function transformProcessingRun(AiProcessingRun $run): array
    {
        $remainingPairs = max(0, $run->total_work_units - $run->completed_work_units - $run->failed_work_units);
        $nextEligiblePair = $run->plagiarismPairs()
            ->where('status', 'pending')
            ->whereNotNull('next_eligible_at')
            ->orderBy('next_eligible_at')
            ->first(['next_eligible_at']);

        return [
            'status' => $run->status,
            'preparation_status' => $run->preparation_status,
            'total_pairs' => $run->total_work_units,
            'completed_pairs' => $run->completed_work_units,
            'failed_pairs' => $run->failed_work_units,
            'remaining_pairs' => $remainingPairs,
            'progress_percentage' => $run->total_work_units > 0
                ? round(($run->completed_work_units / $run->total_work_units) * 100, 2)
                : 100,
            'error_code' => $run->error_code,
            'next_eligible_at' => $nextEligiblePair?->next_eligible_at?->toIso8601String(),
            'published_at' => $run->published_at,
            'can_continue' => $run->status === AiProcessingRun::STATUS_PENDING
                && $run->preparation_status === AiProcessingRun::PREPARATION_PREPARED
                && $remainingPairs > 0
                && (! $nextEligiblePair || $nextEligiblePair->next_eligible_at->lessThanOrEqualTo(now())),
        ];
    }

    private function processingOutcomeMessage(AiProcessingRun $run, string $outcome): string
    {
        if ($outcome === 'failed') {
            return match ($run->error_code) {
                'provider_unavailable' => 'Gemini remained unavailable after the allowed retries. Start a new analysis when the provider recovers.',
                'provider_rate_limited' => 'Gemini rate limits persisted after the allowed retries. Start a new analysis later.',
                default => 'This plagiarism comparison failed and cannot be retried in this run.',
            };
        }

        return match ($run->error_code) {
            'provider_unavailable' => 'Gemini is temporarily unavailable. The comparison was deferred for retry.',
            'provider_rate_limited' => 'Gemini rate limits delayed this comparison. It can be retried after the scheduled time.',
            'budget_exhausted' => 'The configured AI processing budget is exhausted. No comparison was completed.',
            default => 'No plagiarism comparison was completed. Refresh the processing status before trying again.',
        };
    }

    /**
     * Transform an assignment for JSON response.
     */
    private function transformAssignment(
        Assignment $assignment
    ): array {
        return [
            'id' => $this->encryptId($assignment->id),
            'section_id' => $this->encryptId($assignment->section_id),

            'title' => $assignment->title,
            'description' => $assignment->description,
            'type' => $assignment->type,
            'due_date' => $assignment->due_date,
            'created_at' => $assignment->created_at,

            'section' => $assignment->section
                ? [
                    'id' => $this->encryptId($assignment->section->id),
                    'name' => $assignment->section->name,

                    'subject' => $assignment->section->subject
                        ? [
                            'id' => $this->encryptId(
                                $assignment->section->subject->id
                            ),
                            'code' => $assignment->section->subject->code,
                            'name' => $assignment->section->subject->name,
                        ]
                        : null,
                ]
                : null,
        ];
    }

    /**
     * Transform a student for JSON response.
     */
    private function transformStudent($student): array
    {
        return [
            'id' => $this->encryptId($student->id),
            'student_number' => $student->student_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'middle_name' => $student->middle_name,
        ];
    }

    /**
     * Decrypt an encrypted database ID.
     */
    private function decryptId(string $encryptedId): int
    {
        try {
            return (int) Crypt::decryptString($encryptedId);
        } catch (\Throwable $e) {
            abort(404, 'Invalid resource identifier.');
        }
    }

    /**
     * Encrypt a database ID.
     */
    private function encryptId(int $id): string
    {
        return Crypt::encryptString((string) $id);
    }
}
