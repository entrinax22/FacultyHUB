<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesStudent;
use App\Jobs\GradeCodeJob;
use App\Jobs\GradeEssayJob;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Submission;
use App\Models\ProctoringEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    use ResolvesStudent;

    public function create(Request $request, Assignment $assignment): Response
    {
        $student = $this->resolveStudent($request);

        if (! $assignment->is_published) {
            abort(404);
        }

        $existing = Submission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        $assignment->load(['section.subject', 'section.semester', 'questions.choices']);

        $isMonitoredExam = $assignment->isMonitoredExam();

        if ($isMonitoredExam && ! $existing?->terms_accepted_at) {
            $assignment->setRelation('questions', collect());
        }

        return Inertia::render('student/SubmitAssignment', [
            'assignment' => $assignment,
            'existing' => $existing,
            'exam' => $isMonitoredExam ? [
                'duration_minutes' => $assignment->duration_minutes,
                'started_at' => $existing?->started_at?->toIso8601String(),
                'expires_at' => $existing?->expires_at?->toIso8601String(),
                'terms_accepted_at' => $existing?->terms_accepted_at?->toIso8601String(),
                'proctoring_enabled' => $assignment->proctoring_enabled,
            ] : null,
        ]);
    }

    public function startExam(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $this->resolveStudent($request);

        abort_unless($assignment->is_published && $assignment->isMonitoredExam(), 404);

        $validated = $request->validate(['terms_accepted' => ['required', 'accepted']]);

        $submission = Submission::firstOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            ['status' => 'pending']
        );

        if ($submission->isApproved()) {
            return back()->withErrors(['exam' => 'This exam attempt is already submitted.']);
        }

        if (! $submission->started_at) {
            $startedAt = now();
            $submission->update([
                'started_at' => $startedAt,
                'expires_at' => $assignment->duration_minutes ? $startedAt->copy()->addMinutes($assignment->duration_minutes) : null,
                'terms_accepted_at' => now(),
            ]);

            $submission->proctoringEvents()->create([
                'event_type' => 'exam_started',
                'metadata' => [
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'ip_hash' => hash('sha256', (string) $request->ip()),
                ],
            ]);
        }

        return back()->with('success', 'Exam started. Your attempt is being monitored.');
    }

    public function recordProctoringEvent(Request $request, Submission $submission): JsonResponse
    {
        $student = $this->resolveStudent($request);

        abort_unless($submission->student_id === $student->id, 403);

        abort_unless($submission->isActiveExamAttempt(), 422, 'This exam attempt is not active.');

        $validated = $request->validate([
            'events' => ['nullable', 'array', 'max:20'],
            'events.*.event_type' => ['required_with:events', 'in:heartbeat,tab_hidden,tab_visible,window_resized,fullscreen_entered,fullscreen_exited,copy_detected,paste_detected,cut_detected,context_menu_used,print_screen_suspected,camera_permission_denied'],
            'events.*.metadata' => ['nullable', 'array'],
            'event_type' => ['nullable', 'in:heartbeat,tab_hidden,tab_visible,window_resized,fullscreen_entered,fullscreen_exited,copy_detected,paste_detected,cut_detected,context_menu_used,print_screen_suspected,camera_permission_denied'],
            'metadata' => ['nullable', 'array'],
        ]);

        $events = $validated['events'] ?? [[
            'event_type' => $validated['event_type'],
            'metadata' => $validated['metadata'] ?? [],
        ]];

        $submission->proctoringEvents()->createMany($events);

        return response()->json(['stored' => count($events)], 201);
    }

    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $this->resolveStudent($request);

        if (! $assignment->is_published) {
            abort(404);
        }

        $existing = Submission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        if ($assignment->isPastDue()) {
            return back()->withErrors(['due_date' => 'This assignment is past its due date.']);
        }

        if ($assignment->isMonitoredExam()) {
            if (! $existing?->started_at) {
                return back()->withErrors(['exam' => 'Start the exam before submitting your answers.']);
            }

            if ($existing->expires_at && now()->greaterThan($existing->expires_at)) {
                return back()->withErrors(['exam' => 'Your exam time has expired.']);
            }
        }

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
        $student = $this->resolveStudent($request);

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
