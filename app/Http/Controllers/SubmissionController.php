<?php

namespace App\Http\Controllers;

use App\Jobs\GradeCodeJob;
use App\Jobs\GradeEssayJob;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function create(Request $request, Assignment $assignment): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            abort(403);
        }

        if (! $assignment->is_published) {
            abort(404);
        }

        $existing = Submission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        $assignment->load(['section.subject', 'section.semester', 'questions.choices']);

        return Inertia::render('student/SubmitAssignment', [
            'assignment' => $assignment,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            abort(403);
        }

        if (! $assignment->is_published) {
            abort(404);
        }

        if ($assignment->isPastDue()) {
            return back()->withErrors(['due_date' => 'This assignment is past its due date.']);
        }

        $existing = Submission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing && $existing->isApproved()) {
            return back()->withErrors(['submission' => 'Your submission has already been graded and approved.']);
        }

        match ($assignment->type) {
            'essay' => $this->handleEssaySubmission($request, $assignment, $student, $existing),
            'mcq' => $this->handleMcqSubmission($request, $assignment, $student, $existing),
            'code' => $this->handleCodeSubmission($request, $assignment, $student, $existing),
        };

        return redirect()
            ->route('student.submissions', $assignment->section_id)
            ->with('success', 'Submission received!');
    }

    private function handleEssaySubmission(Request $request, Assignment $assignment, \App\Models\Student $student, ?Submission $existing): void
    {
        $validated = $request->validate(['content' => 'required|string|min:10']);

        $submission = $existing
            ? tap($existing)->update(['content' => $validated['content'], 'status' => 'grading', 'submitted_at' => now()])
            : Submission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'content' => $validated['content'],
                'status' => 'grading',
            ]);

        GradeEssayJob::dispatch($submission);
    }

    private function handleMcqSubmission(Request $request, Assignment $assignment, \App\Models\Student $student, ?Submission $existing): void
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'integer',
        ]);

        $submission = $existing
            ? tap($existing)->update(['answers' => $validated['answers'], 'submitted_at' => now()])
            : Submission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'answers' => $validated['answers'],
                'status' => 'pending',
            ]);

        $this->autoGradeMcq($submission, $assignment);
    }

    private function handleCodeSubmission(Request $request, Assignment $assignment, \App\Models\Student $student, ?Submission $existing): void
    {
        $validated = $request->validate(['content' => 'required|string|min:1']);

        $submission = $existing
            ? tap($existing)->update(['content' => $validated['content'], 'status' => 'grading', 'submitted_at' => now()])
            : Submission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'content' => $validated['content'],
                'status' => 'grading',
            ]);

        GradeCodeJob::dispatch($submission);
    }

    private function autoGradeMcq(Submission $submission, Assignment $assignment): void
    {
        $answers = $submission->answers ?? [];
        $questions = $assignment->questions()->with('correctChoice')->get();
        $correct = 0;
        $total = $questions->count();

        foreach ($questions as $question) {
            $chosen = $answers[$question->id] ?? null;
            if ($chosen && $question->correctChoice && (int) $chosen === $question->correctChoice->id) {
                $correct++;
            }
        }

        $score = $total > 0 ? ($correct / $total) * $assignment->max_score : 0;

        Grade::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'student_id' => $submission->student_id,
                'section_id' => $assignment->section_id,
                'assignment_id' => $assignment->id,
                'raw_score' => round($score, 2),
                'max_score' => $assignment->max_score,
                'remarks' => "{$correct}/{$total} correct answers",
                'is_released' => false,
            ]
        );

        $submission->update(['status' => 'approved']);
    }

    public function studentAssignments(Request $request, \App\Models\Section $section): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            abort(403);
        }

        $section->load(['subject', 'semester']);

        $assignments = $section->assignments()
            ->where('is_published', true)
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)->with('grade')])
            ->latest()
            ->get()
            ->map(function (Assignment $a) {
                $sub = $a->submissions->first();

                return array_merge($a->toArray(), [
                    'my_submission' => $sub,
                    'my_grade' => $sub?->grade,
                ]);
            });

        return Inertia::render('student/Assignments', [
            'section' => $section,
            'assignments' => $assignments,
        ]);
    }

    public function show(Submission $submission): Response
    {
        $submission->load(['assignment.section.subject', 'grade', 'aiFeedback', 'assignment.questions.choices']);

        $answersReleased = $submission->assignment->answersReleased();

        return Inertia::render('student/SubmissionResult', [
            'submission' => $submission,
            'answersReleased' => $answersReleased,
        ]);
    }
}
