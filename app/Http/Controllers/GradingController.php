<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class GradingController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    */

    public function show(string $id): Response
    {
        $submission = $this->resolveSubmission($id);

        return Inertia::render('assignments/Grading', [
            'submissionId' => $this->encryptId(
                $submission->id
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    public function data(string $id): JsonResponse
    {
        $submission = $this->resolveSubmission($id);

        $submission->load([
            'assignment.section.subject',
            'assignment.questions.choices',
            'student',
            'grade',
            'aiFeedback',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Grading data loaded successfully.',

            'data' => [
                'submission' =>
                    $this->transformSubmission($submission),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Approve and Save Grade
    |--------------------------------------------------------------------------
    */

    public function approve(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $submission = $this->resolveSubmission($id);

            $validated = $request->validate([
                'raw_score' => 'required|numeric|min:0',
                'remarks' => 'nullable|string|max:1000',
            ]);

            $grade = Grade::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                ],
                [
                    'student_id' =>
                        $submission->student_id,

                    'section_id' =>
                        $submission->assignment->section_id,

                    'assignment_id' =>
                        $submission->assignment_id,

                    'raw_score' =>
                        $validated['raw_score'],

                    'max_score' =>
                        $submission->assignment->max_score,

                    'remarks' =>
                        $validated['remarks'] ?? null,

                    'is_released' => false,
                ]
            );

            $submission->update([
                'status' => 'approved',
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Grade approved successfully.',

                'data' => [
                    'submission_id' =>
                        $this->encryptId(
                            $submission->id
                        ),

                    'grade' => [
                        'id' =>
                            $this->encryptId(
                                $grade->id
                            ),

                        'raw_score' =>
                            $grade->raw_score,

                        'max_score' =>
                            $grade->max_score,

                        'remarks' =>
                            $grade->remarks,

                        'is_released' =>
                            (bool) $grade->is_released,
                    ],

                    'status' =>
                        $submission->status,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to approve grade.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Release Grades
    |--------------------------------------------------------------------------
    */

    public function releaseGrades(
        Request $request
    ): JsonResponse {
        try {
            $validated = $request->validate([
                'assignment_id' =>
                    'required|string',
            ]);

            $assignmentId = $this->decryptId(
                $validated['assignment_id']
            );

            $updated = Grade::where(
                'assignment_id',
                $assignmentId
            )->update([
                'is_released' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'All grades released to students.',

                'data' => [
                    'updated_count' =>
                        $updated,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to release grades.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Submission
    |--------------------------------------------------------------------------
    */

    private function resolveSubmission(
        string $encryptedId
    ): Submission {
        $id = $this->decryptId(
            $encryptedId
        );

        return Submission::query()
            ->findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | Decrypt ID
    |--------------------------------------------------------------------------
    */

    private function decryptId(
        string $encryptedId
    ): int {
        try {
            return (int) Crypt::decryptString(
                $encryptedId
            );
        } catch (\Throwable $e) {
            abort(
                404,
                'Invalid resource identifier.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Encrypt ID
    |--------------------------------------------------------------------------
    */

    private function encryptId(
        int $id
    ): string {
        return Crypt::encryptString(
            (string) $id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Submission
    |--------------------------------------------------------------------------
    */

    private function transformSubmission(
        Submission $submission
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Stored MCQ answers
        |--------------------------------------------------------------------------
        |
        | The SubmissionController stores MCQ answers as:
        |
        | [
        |     question_id => choice_id,
        | ]
        |
        */
        $answers = $submission->answers ?? [];

        return [
            'id' => $this->encryptId(
                $submission->id
            ),

            'status' =>
                $submission->status,

            'content' =>
                $submission->content,

            'submitted_at' =>
                $submission->submitted_at,

            /*
            |--------------------------------------------------------------------------
            | Student
            |--------------------------------------------------------------------------
            */

            'student' => $submission->student
                ? [
                    'id' => $this->encryptId(
                        $submission->student->id
                    ),

                    'student_no' =>
                        $submission->student->student_no,

                    'first_name' =>
                        $submission->student->first_name,

                    'last_name' =>
                        $submission->student->last_name,
                ]
                : null,

            /*
            |--------------------------------------------------------------------------
            | Assignment
            |--------------------------------------------------------------------------
            */

            'assignment' => $submission->assignment
                ? [
                    'id' => $this->encryptId(
                        $submission->assignment->id
                    ),

                    'title' =>
                        $submission->assignment->title,

                    'type' =>
                        $submission->assignment->type,

                    'max_score' =>
                        $submission->assignment->max_score,

                    'section' =>
                        $submission->assignment->section
                            ? [
                                'id' => $this->encryptId(
                                    $submission
                                        ->assignment
                                        ->section
                                        ->id
                                ),

                                'name' =>
                                    $submission
                                        ->assignment
                                        ->section
                                        ->name,

                                'subject' =>
                                    $submission
                                        ->assignment
                                        ->section
                                        ->subject
                                        ? [
                                            'id' =>
                                                $this->encryptId(
                                                    $submission
                                                        ->assignment
                                                        ->section
                                                        ->subject
                                                        ->id
                                                ),

                                            'code' =>
                                                $submission
                                                    ->assignment
                                                    ->section
                                                    ->subject
                                                    ->code,

                                            'name' =>
                                                $submission
                                                    ->assignment
                                                    ->section
                                                    ->subject
                                                    ->name,
                                        ]
                                        : null,
                            ]
                            : null,
                ]
                : null,

            /*
            |--------------------------------------------------------------------------
            | Questions
            |--------------------------------------------------------------------------
            */

            'questions' => collect(
                $submission->assignment?->questions ?? []
            )
                ->map(function ($question) use ($answers) {

                    /*
                    |--------------------------------------------------------------------------
                    | Get the student's selected choice
                    |--------------------------------------------------------------------------
                    |
                    | Example:
                    |
                    | $answers = [
                    |     15 => 32,
                    | ];
                    |
                    | 15 = question ID
                    | 32 = selected choice ID
                    |
                    */

                    $selectedChoiceId =
                        $answers[$question->id] ?? null;

                    return [
                        'id' =>
                            $this->encryptId(
                                $question->id
                            ),

                        /*
                        |--------------------------------------------------------------------------
                        | IMPORTANT
                        |--------------------------------------------------------------------------
                        |
                        | The actual database column is `question`,
                        | not `question_text`.
                        |
                        */

                        'question' =>
                            $question->question,

                        'points' =>
                            $question->points,

                        /*
                        |--------------------------------------------------------------------------
                        | Selected Choice
                        |--------------------------------------------------------------------------
                        */

                        'selected_choice_id' =>
                            $selectedChoiceId !== null
                                ? $this->encryptId(
                                    $selectedChoiceId
                                )
                                : null,

                        /*
                        |--------------------------------------------------------------------------
                        | Choices
                        |--------------------------------------------------------------------------
                        */

                        'choices' => collect(
                            $question->choices ?? []
                        )
                            ->map(function ($choice) use (
                                $selectedChoiceId
                            ) {
                                return [
                                    'id' =>
                                        $this->encryptId(
                                            $choice->id
                                        ),

                                    'choice_text' =>
                                        $choice->choice_text,

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Correct answer
                                    |--------------------------------------------------------------------------
                                    */

                                    'is_correct' =>
                                        (bool) $choice->is_correct,

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Student's selected answer
                                    |--------------------------------------------------------------------------
                                    |
                                    | Compare the ORIGINAL integer IDs
                                    | before encryption.
                                    |
                                    */

                                    'is_selected' =>
                                        $selectedChoiceId !== null &&
                                        (int) $selectedChoiceId ===
                                        (int) $choice->id,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),

            /*
            |--------------------------------------------------------------------------
            | Grade
            |--------------------------------------------------------------------------
            */

            'grade' => $submission->grade
                ? [
                    'id' =>
                        $this->encryptId(
                            $submission->grade->id
                        ),

                    'raw_score' =>
                        $submission->grade->raw_score,

                    'max_score' =>
                        $submission->grade->max_score,

                    'percentage' =>
                        $submission->grade->percentage,

                    'status' =>
                        $submission->grade->status,

                    'remarks' =>
                        $submission->grade->remarks,

                    'is_released' =>
                        (bool) $submission
                            ->grade
                            ->is_released,
                ]
                : null,

            /*
            |--------------------------------------------------------------------------
            | AI Feedback
            |--------------------------------------------------------------------------
            */

            'ai_feedback' =>
                $submission->aiFeedback
                    ? [
                        'id' =>
                            $this->encryptId(
                                $submission->aiFeedback->id
                            ),

                        'score' =>
                            $submission->aiFeedback->score,

                        'feedback' =>
                            $submission->aiFeedback->feedback_json,
                    ]
                    : null,
        ];
    }
}
