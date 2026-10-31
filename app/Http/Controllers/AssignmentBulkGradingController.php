<?php

namespace App\Http\Controllers;

use App\Models\AiGradingRunSubmission;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\PlagiarismReport;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentBulkGradingController extends Controller
{
    private const CHUNK_SIZE = 80;

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $assignment = $this->resolveAssignment($id);
        abort_unless(
            ! $request->user()?->isFaculty()
                || (int) $assignment->section?->faculty_id === (int) $request->user()->id,
            403
        );

        $validated = $request->validate([
            'operation' => ['required', 'in:preview,approve'],
            'mode' => ['required', 'in:selected,all'],
            'submission_ids' => ['required_if:mode,selected', 'array', 'max:1000'],
            'submission_ids.*' => ['required', 'string', 'distinct'],
        ]);

        $selectedIds = $validated['mode'] === 'selected'
            ? $this->resolveSubmissionIds($validated['submission_ids'] ?? [], $assignment)
            : null;

        if ($validated['operation'] === 'preview') {
            $counts = [
                'eligible' => 0,
                'already_approved' => 0,
                'requires_individual_review' => 0,
            ];
            $eligibleIds = [];
            $this->eachSubmissionChunk($assignment, $selectedIds, function ($submissions) use (&$counts, &$eligibleIds, $assignment, $selectedIds) {
                $blocked = $this->blockedSubmissionIds($assignment, $submissions);
                foreach ($submissions as $submission) {
                    $reason = $this->ineligibilityReason($submission, $blocked, $assignment->max_score);
                    if ($reason === 'already_approved') {
                        $counts['already_approved']++;
                    } elseif ($reason === null) {
                        $counts['eligible']++;
                        if ($selectedIds !== null) {
                            $eligibleIds[] = Crypt::encryptString((string) $submission->id);
                        }
                    } else {
                        $counts['requires_individual_review']++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Bulk approval preview is ready.',
                'data' => [...$counts, 'eligible_ids' => $eligibleIds],
            ]);
        }

        $result = [
            'approved' => 0,
            'already_approved' => 0,
            'skipped' => 0,
            'failed' => 0,
            'reasons' => [],
        ];

        $this->eachSubmissionChunk($assignment, $selectedIds, function ($submissions) use ($assignment) {
            $blocked = $this->blockedSubmissionIds($assignment, $submissions);
            $chunkResult = ['approved' => 0, 'already_approved' => 0, 'skipped' => 0, 'reasons' => []];
            $approveIds = [];
            $newGrades = [];
            $timestamp = now();

            foreach ($submissions as $submission) {
                $reason = $this->ineligibilityReason($submission, $blocked, $assignment->max_score);
                if ($reason === 'already_approved') {
                    $chunkResult['already_approved']++;
                    continue;
                }
                if ($reason !== null) {
                    $chunkResult['skipped']++;
                    $chunkResult['reasons'][$reason] = ($chunkResult['reasons'][$reason] ?? 0) + 1;
                    continue;
                }

                $approveIds[] = $submission->id;
                if (! $submission->grade) {
                    $newGrades[] = [
                        'submission_id' => $submission->id,
                        'student_id' => $submission->student_id,
                        'section_id' => $assignment->section_id,
                        'assignment_id' => $assignment->id,
                        'raw_score' => $submission->aiFeedback->score,
                        'max_score' => $assignment->max_score,
                        'remarks' => null,
                        'is_released' => false,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
                $chunkResult['approved']++;
            }

            if ($newGrades !== []) {
                Grade::query()->insert($newGrades);
            }
            if ($approveIds !== []) {
                Submission::query()->whereIn('id', $approveIds)->update([
                    'status' => 'approved',
                    'updated_at' => $timestamp,
                ]);
            }

            return $chunkResult;
        }, true, function ($chunkResult) use (&$result) {
            foreach (['approved', 'already_approved', 'skipped'] as $key) {
                $result[$key] += $chunkResult[$key];
            }
            foreach ($chunkResult['reasons'] as $reason => $count) {
                $result['reasons'][$reason] = ($result['reasons'][$reason] ?? 0) + $count;
            }
        }, function ($ids, $exception) use (&$result) {
            report($exception);
            $result['failed'] += count($ids);
            $result['reasons']['save_failed'] = ($result['reasons']['save_failed'] ?? 0) + count($ids);
        });

        $reasonLabels = [
            'academic_integrity_flag' => 'Academic integrity flag requires individual review',
            'grading_incomplete' => 'Grading is incomplete',
            'unresolved_grading_issue' => 'AI grading has an unresolved issue',
            'valid_grade_unavailable' => 'No valid grade or score is available',
            'save_failed' => 'The grade batch could not be saved',
        ];
        $result['reasons'] = collect($result['reasons'])
            ->mapWithKeys(fn ($count, $reason) => [$reasonLabels[$reason] ?? 'Another review issue requires attention' => $count])
            ->all();

        return response()->json([
            'success' => true,
            'message' => "Approved {$result['approved']}; already approved {$result['already_approved']}; skipped {$result['skipped']}; failed {$result['failed']}.",
            'data' => [
                ...$result,
                'status_counts' => $assignment->submissions()
                    ->selectRaw('status, COUNT(*) as aggregate')
                    ->groupBy('status')
                    ->pluck('aggregate', 'status'),
            ],
        ]);
    }

    private function eachSubmissionChunk(Assignment $assignment, ?array $selectedIds, callable $callback, bool $lock = false, ?callable $onSuccess = null, ?callable $onFailure = null): void
    {
        $query = Submission::query()->where('assignment_id', $assignment->id);
        if ($selectedIds !== null) {
            $query->whereIn('id', $selectedIds);
        }

        $query->select('id');
        $query->chunkById(self::CHUNK_SIZE, function ($items) use ($assignment, $callback, $lock, $onSuccess, $onFailure) {
            $ids = $items->pluck('id')->all();
            $work = function () use ($assignment, $ids, $callback, $lock) {
                $submissions = Submission::query()
                    ->with(['grade', 'aiFeedback'])
                    ->where('assignment_id', $assignment->id)
                    ->whereIn('id', $ids)
                    ->orderBy('id');
                if ($lock) {
                    $submissions->lockForUpdate();
                }
                return $callback($submissions->get());
            };

            if ($lock) {
                $value = null;
                try {
                    $value = DB::transaction($work);
                } catch (\Throwable $exception) {
                    if (! $onFailure) {
                        throw $exception;
                    }
                    $onFailure($ids, $exception);
                }
                if ($value !== null) {
                    $onSuccess?->__invoke($value);
                }
            } else {
                $work();
            }
        });
    }

    private function blockedSubmissionIds(Assignment $assignment, $submissions): array
    {
        $submissionIds = $submissions->pluck('id')->all();
        $studentIds = $submissions->pluck('student_id')->all();

        $flaggedStudents = PlagiarismReport::query()
            ->where('assignment_id', $assignment->id)
            ->where('flagged', true)
            ->where(function ($query) use ($studentIds) {
                $query->whereIn('student_a_id', $studentIds)
                    ->orWhereIn('student_b_id', $studentIds);
            })
            ->get(['student_a_id', 'student_b_id'])
            ->flatMap(fn ($report) => [$report->student_a_id, $report->student_b_id])
            ->all();

        $flaggedIds = $submissions
            ->whereIn('student_id', $flaggedStudents)
            ->pluck('id')
            ->all();

        $unresolvedIds = AiGradingRunSubmission::query()
            ->whereIn('submission_id', $submissionIds)
            ->where(function ($query) {
                $query->where('status', '!=', AiGradingRunSubmission::STATUS_COMPLETED)
                    ->orWhereNotNull('error_code');
            })
            ->pluck('submission_id')
            ->all();

        return [
            'integrity' => array_fill_keys($flaggedIds, true),
            'grading' => array_fill_keys($unresolvedIds, true),
        ];
    }

    private function ineligibilityReason(Submission $submission, array $blocked, float|int|string $maxScore): ?string
    {
        if ($submission->status === 'approved') {
            return 'already_approved';
        }
        if (isset($blocked['integrity'][$submission->id])) {
            return 'academic_integrity_flag';
        }
        if (isset($blocked['grading'][$submission->id])) {
            return 'unresolved_grading_issue';
        }
        if ($submission->status !== 'graded') {
            return 'grading_incomplete';
        }

        $score = $submission->grade?->raw_score ?? $submission->aiFeedback?->score;
        if ($score === null || ! is_numeric($score) || (float) $score < 0 || (float) $score > (float) $maxScore) {
            return 'valid_grade_unavailable';
        }

        return null;
    }

    private function resolveAssignment(string $encryptedId): Assignment
    {
        try {
            return Assignment::query()->with('section')->findOrFail(Crypt::decryptString($encryptedId));
        } catch (\Throwable) {
            abort(404, 'Invalid assignment identifier.');
        }
    }

    private function resolveSubmissionIds(array $encryptedIds, Assignment $assignment): array
    {
        $ids = [];
        foreach ($encryptedIds as $encryptedId) {
            try {
                $ids[] = (int) Crypt::decryptString($encryptedId);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['submission_ids' => 'One or more selected submissions are invalid.']);
            }
        }
        $ids = array_values(array_unique($ids));

        if (count($ids) !== Submission::query()->where('assignment_id', $assignment->id)->whereIn('id', $ids)->count()) {
            throw ValidationException::withMessages(['submission_ids' => 'Selected submissions must belong to this assignment.']);
        }

        return $ids;
    }
}
