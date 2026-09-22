<?php

namespace App\Http\Controllers;

use App\Jobs\CheckPlagiarismJob;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class PlagiarismController extends Controller
{
    /**
     * Render the plagiarism page.
     */
    public function show(string $assignmentId): Response
    {
        $assignment = $this->resolveAssignment($assignmentId);

        return Inertia::render('Plagiarism/Index', [
            'assignmentId' => $this->encryptId($assignment->id),
        ]);
    }

    /**
     * Load plagiarism data for Axios.
     */
    public function data(string $assignmentId): JsonResponse
    {
        $assignment = $this->resolveAssignment($assignmentId);

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
                        'created_at' => $report->created_at,
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Queue a plagiarism check for an assignment.
     */
    public function run(string $assignmentId): JsonResponse
    {
        $assignment = $this->resolveAssignment($assignmentId);

        $count = $assignment->submissions()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->count();

        if ($count < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Need at least 2 text submissions to check for plagiarism.',
            ], 422);
        }

        CheckPlagiarismJob::dispatch($assignment);

        return response()->json([
            'success' => true,
            'message' => 'Plagiarism check queued. Results will appear shortly.',
        ]);
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