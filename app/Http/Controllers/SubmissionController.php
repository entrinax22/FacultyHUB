<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesStudent;
use App\Jobs\GradeCodeJob;
use App\Jobs\GradeEssayJob;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Student;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    use ResolvesStudent;

    /*
    |--------------------------------------------------------------------------
    | Submit Assignment Page
    |--------------------------------------------------------------------------
    */

    public function create(
        Request $request,
        string $assignmentId
    ): Response {
        $student = $this->resolveStudent($request);

        $assignment = $this->resolveAssignment($assignmentId);

        if (! $assignment->is_published) {
            abort(404);
        }

        $this->ensureStudentEnrolled(
            $student->id,
            $assignment->section_id
        );

        $existing = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        $assignment->load([
            'section.subject',
            'section.semester',
            'questions.choices',
        ]);

        $isMonitoredExam = $assignment->isMonitoredExam();

        /*
         * For monitored exams, do not expose questions until
         * the student accepts the terms and starts the exam.
         */
        if (
            $isMonitoredExam &&
            ! $existing?->terms_accepted_at
        ) {
            $assignment->setRelation(
                'questions',
                collect()
            );
        }

        return Inertia::render(
            'student/SubmitAssignment',
            [
                'assignment' =>
                    $this->transformAssignmentForStudent(
                        $assignment
                    ),

                'existing' => $existing
                    ? $this->transformExistingSubmission(
                        $existing
                    )
                    : null,

                'exam' => $isMonitoredExam
                    ? [
                        'duration_minutes' =>
                            $assignment->duration_minutes,

                        'started_at' =>
                            $existing?->started_at
                                ?->toIso8601String(),

                        'expires_at' =>
                            $existing?->expires_at
                                ?->toIso8601String(),

                        'terms_accepted_at' =>
                            $existing?->terms_accepted_at
                                ?->toIso8601String(),

                        'proctoring_enabled' =>
                            (bool) $assignment->proctoring_enabled,
                    ]
                    : null,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Start Monitored Exam
    |--------------------------------------------------------------------------
    */

    public function startExam(
        Request $request,
        string $assignmentId
    ): JsonResponse {
        try {
            $student = $this->resolveStudent($request);

            $assignment = $this->resolveAssignment(
                $assignmentId
            );

            abort_unless(
                $assignment->is_published &&
                $assignment->isMonitoredExam(),
                404
            );

            $this->ensureStudentEnrolled(
                $student->id,
                $assignment->section_id
            );

            $request->validate([
                'terms_accepted' => [
                    'required',
                    'accepted',
                ],
            ]);

            $submission = Submission::firstOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                ],
                [
                    'status' => 'pending',
                ]
            );

            if ($submission->isApproved()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This exam attempt is already submitted.',
                ], 422);
            }

            if (! $submission->started_at) {
                $startedAt = now();

                $submission->update([
                    'started_at' => $startedAt,

                    'expires_at' =>
                        $assignment->duration_minutes
                            ? $startedAt
                                ->copy()
                                ->addMinutes(
                                    $assignment->duration_minutes
                                )
                            : null,

                    'terms_accepted_at' => now(),
                ]);

                $submission
                    ->proctoringEvents()
                    ->create([
                        'event_type' => 'exam_started',

                        'metadata' => [
                            'user_agent' =>
                                substr(
                                    (string) $request->userAgent(),
                                    0,
                                    500
                                ),

                            'ip_hash' =>
                                hash(
                                    'sha256',
                                    (string) $request->ip()
                                ),
                        ],
                    ]);
            }

            $submission->refresh();

            return response()->json([
                'success' => true,

                'message' =>
                    'Exam started. Your attempt is being monitored.',

                'data' => [
                    'submission_id' =>
                        $this->encryptId(
                            $submission->id
                        ),

                    'started_at' =>
                        $submission
                            ->started_at
                            ?->toIso8601String(),

                    'expires_at' =>
                        $submission
                            ->expires_at
                            ?->toIso8601String(),

                    'terms_accepted_at' =>
                        $submission
                            ->terms_accepted_at
                            ?->toIso8601String(),

                    'status' =>
                        $submission->status,
                ],
            ]);

        } catch (ValidationException $e) {
            throw $e;

        } catch (
            \Symfony\Component\HttpKernel\Exception\HttpException $e
        ) {
            throw $e;

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to start the exam.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Proctoring Events
    |--------------------------------------------------------------------------
    */

    public function recordProctoringEvent(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $student = $this->resolveStudent($request);

            $submission = $this->resolveSubmission($id);

            abort_unless(
                $submission->student_id === $student->id,
                403
            );

            abort_unless(
                $submission->isActiveExamAttempt(),
                422,
                'This exam attempt is not active.'
            );

            $validated = $request->validate([
                'events' => [
                    'nullable',
                    'array',
                    'max:20',
                ],

                'events.*.event_type' => [
                    'required_with:events',
                    'in:heartbeat,tab_hidden,tab_visible,window_resized,fullscreen_entered,fullscreen_exited,copy_detected,paste_detected,cut_detected,context_menu_used,print_screen_suspected,camera_permission_denied',
                ],

                'events.*.metadata' => [
                    'nullable',
                    'array',
                ],

                'event_type' => [
                    'nullable',
                    'in:heartbeat,tab_hidden,tab_visible,window_resized,fullscreen_entered,fullscreen_exited,copy_detected,paste_detected,cut_detected,context_menu_used,print_screen_suspected,camera_permission_denied',
                ],

                'metadata' => [
                    'nullable',
                    'array',
                ],
            ]);

            $events = $validated['events'] ?? [
                [
                    'event_type' =>
                        $validated['event_type'] ?? null,

                    'metadata' =>
                        $validated['metadata'] ?? [],
                ],
            ];

            $events = collect($events)
                ->filter(
                    fn ($event) =>
                        ! empty($event['event_type'])
                )
                ->values()
                ->all();

            if (empty($events)) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'No valid proctoring events were provided.',
                ], 422);
            }

            $submission
                ->proctoringEvents()
                ->createMany($events);

            return response()->json([
                'success' => true,

                'message' =>
                    'Proctoring events recorded successfully.',

                'data' => [
                    'stored' => count($events),
                ],
            ], 201);

        } catch (ValidationException $e) {
            throw $e;

        } catch (
            \Symfony\Component\HttpKernel\Exception\HttpException $e
        ) {
            throw $e;

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to record proctoring events.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Assignment
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        string $assignmentId
    ): JsonResponse {
        try {
            $student = $this->resolveStudent($request);

            $assignment = $this->resolveAssignment(
                $assignmentId
            );

            if (! $assignment->is_published) {
                abort(404);
            }

            $this->ensureStudentEnrolled(
                $student->id,
                $assignment->section_id
            );

            $existing = Submission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->first();

            if ($assignment->isPastDue()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This assignment is past its due date.',
                ], 422);
            }

            if ($assignment->isMonitoredExam()) {
                if (! $existing?->started_at) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Start the exam before submitting your answers.',
                    ], 422);
                }

                if (
                    $existing->expires_at &&
                    now()->greaterThan(
                        $existing->expires_at
                    )
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Your exam time has expired.',
                    ], 422);
                }
            }

            if (
                $existing &&
                $existing->isApproved()
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Your submission has already been graded and approved.',
                ], 422);
            }

            $submission = match ($assignment->type) {
                'essay' =>
                    $this->handleEssaySubmission(
                        $request,
                        $assignment,
                        $student,
                        $existing
                    ),

                'mcq' =>
                    $this->handleMcqSubmission(
                        $request,
                        $assignment,
                        $student,
                        $existing
                    ),

                'code' =>
                    $this->handleCodeSubmission(
                        $request,
                        $assignment,
                        $student,
                        $existing
                    ),

                default =>
                    throw new \InvalidArgumentException(
                        'Unsupported assignment type.'
                    ),
            };

            $submission->refresh();

            $submission->load('grade');

            return response()->json([
                'success' => true,

                'message' =>
                    'Submission received successfully.',

                'data' => [
                    'submission_id' =>
                        $this->encryptId(
                            $submission->id
                        ),

                    'assignment_id' =>
                        $this->encryptId(
                            $assignment->id
                        ),

                    'status' =>
                        $submission->status,

                    'submitted_at' =>
                        $submission
                            ->submitted_at
                            ?->toIso8601String(),

                    'grade' => $submission->grade
                        ? $this->transformGrade(
                            $submission->grade
                        )
                        : null,
                ],
            ]);

        } catch (ValidationException $e) {
            throw $e;

        } catch (
            \Symfony\Component\HttpKernel\Exception\HttpException $e
        ) {
            throw $e;

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to submit the assignment.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Essay Submission
    |--------------------------------------------------------------------------
    */

    private function handleEssaySubmission(
        Request $request,
        Assignment $assignment,
        Student $student,
        ?Submission $existing
    ): Submission {
        $validated = $request->validate([
            'content' => [
                'required',
                'string',
                'min:10',
            ],
        ]);

        $submission = $existing
            ? tap($existing)->update([
                'content' =>
                    $validated['content'],

                'status' => 'grading',

                'submitted_at' => now(),
            ])

            : Submission::create([
                'assignment_id' =>
                    $assignment->id,

                'student_id' =>
                    $student->id,

                'content' =>
                    $validated['content'],

                'status' => 'grading',

                'submitted_at' => now(),
            ]);

        GradeEssayJob::dispatch($submission);

        return $submission;
    }

    /*
    |--------------------------------------------------------------------------
    | MCQ Submission
    |--------------------------------------------------------------------------
    */

    private function handleMcqSubmission(
        Request $request,
        Assignment $assignment,
        Student $student,
        ?Submission $existing
    ): Submission {
        $validated = $request->validate([
            'answers' => [
                'required',
                'array',
            ],

            'answers.*' => [
                'required',
                'string',
            ],
        ]);

        $answers = [];

        foreach (
            $validated['answers']
            as $encryptedQuestionId => $encryptedChoiceId
        ) {
            $questionId = $this->decryptId(
                $encryptedQuestionId
            );

            $choiceId = $this->decryptId(
                $encryptedChoiceId
            );

            $question = $assignment
                ->questions()
                ->find($questionId);

            if (! $question) {
                abort(
                    422,
                    'Invalid question.'
                );
            }

            $choiceExists = $question
                ->choices()
                ->whereKey($choiceId)
                ->exists();

            if (! $choiceExists) {
                abort(
                    422,
                    'Invalid choice.'
                );
            }

            $answers[$questionId] = $choiceId;
        }

        $submission = $existing
            ? tap($existing)->update([
                'answers' => $answers,

                'status' => 'pending',

                'submitted_at' => now(),
            ])

            : Submission::create([
                'assignment_id' =>
                    $assignment->id,

                'student_id' =>
                    $student->id,

                'answers' =>
                    $answers,

                'status' => 'pending',

                'submitted_at' => now(),
            ]);

        $this->autoGradeMcq(
            $submission,
            $assignment
        );

        return $submission;
    }

    /*
    |--------------------------------------------------------------------------
    | Code Submission
    |--------------------------------------------------------------------------
    */

    private function handleCodeSubmission(
        Request $request,
        Assignment $assignment,
        Student $student,
        ?Submission $existing
    ): Submission {
        $validated = $request->validate([
            'content' => [
                'required',
                'string',
                'min:1',
            ],
        ]);

        $submission = $existing
            ? tap($existing)->update([
                'content' =>
                    $validated['content'],

                'status' => 'grading',

                'submitted_at' => now(),
            ])

            : Submission::create([
                'assignment_id' =>
                    $assignment->id,

                'student_id' =>
                    $student->id,

                'content' =>
                    $validated['content'],

                'status' => 'grading',

                'submitted_at' => now(),
            ]);

        GradeCodeJob::dispatch($submission);

        return $submission;
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic MCQ Grading
    |--------------------------------------------------------------------------
    */

    private function autoGradeMcq(
        Submission $submission,
        Assignment $assignment
    ): Grade {
        $answers = $submission->answers ?? [];

        $questions = $assignment
            ->questions()
            ->with('correctChoice')
            ->get();

        $correct = 0;
        $total = $questions->count();

        foreach ($questions as $question) {
            $chosen =
                $answers[$question->id] ?? null;

            if (
                $chosen &&
                $question->correctChoice &&
                (int) $chosen ===
                (int) $question->correctChoice->id
            ) {
                $correct++;
            }
        }

        $score = $total > 0
            ? ($correct / $total) *
                $assignment->max_score
            : 0;

        $grade = Grade::updateOrCreate(
            [
                'submission_id' =>
                    $submission->id,
            ],
            [
                'student_id' =>
                    $submission->student_id,

                'section_id' =>
                    $assignment->section_id,

                'assignment_id' =>
                    $assignment->id,

                'raw_score' =>
                    round($score, 2),

                'max_score' =>
                    $assignment->max_score,

                'remarks' =>
                    "{$correct}/{$total} correct answers",

                'is_released' => false,
            ]
        );

        $submission->update([
            'status' => 'approved',
        ]);

        return $grade;
    }

    /*
    |--------------------------------------------------------------------------
    | Student Assignments
    |--------------------------------------------------------------------------
    */

    public function studentAssignments(
        Request $request,
        string $sectionId
    ): Response {
        $student =
            $this->resolveStudent($request);

        $section =
            $this->resolveSection($sectionId);

        $this->ensureStudentEnrolled(
            $student->id,
            $section->id
        );

        $section->load([
            'subject',
            'semester',
        ]);

        $assignments = $section
            ->assignments()
            ->where('is_published', true)
            ->with([
                'submissions' => fn ($query) =>
                    $query
                        ->where(
                            'student_id',
                            $student->id
                        )
                        ->with('grade'),
            ])
            ->latest()
            ->get()
            ->map(function (
                Assignment $assignment
            ) {
                $submission =
                    $assignment
                        ->submissions
                        ->first();

                return [
                    'id' =>
                        $this->encryptId(
                            $assignment->id
                        ),

                    'title' =>
                        $assignment->title,

                    'type' =>
                        $assignment->type,

                    'instructions' =>
                        $assignment->instructions,

                    'max_score' =>
                        $assignment->max_score,

                    'due_date' =>
                        $assignment
                            ->due_date
                            ?->toIso8601String(),

                    'language' =>
                        $assignment->language,

                    'is_published' =>
                        (bool) $assignment->is_published,

                    'section_id' =>
                        $this->encryptId(
                            $assignment->section_id
                        ),

                    'my_submission' =>
                        $submission
                            ? $this->transformSubmissionSummary(
                                $submission
                            )
                            : null,

                    'my_grade' =>
                        $submission?->grade
                            ? $this->transformGrade(
                                $submission->grade
                            )
                            : null,
                ];
            })
            ->values();

        return Inertia::render(
            'student/Assignments',
            [
                'section' =>
                    $this->transformSection(
                        $section
                    ),

                'assignments' =>
                    $assignments,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Student Submission Result
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        string $id
    ): Response {
        $student =
            $this->resolveStudent($request);

        $submission =
            $this->resolveSubmission($id);

        abort_unless(
            $submission->student_id === $student->id,
            403
        );

        $submission->load([
            'student',
            'assignment.section.subject',
            'assignment.section.semester',
            'grade',
            'aiFeedback',
            'assignment.questions.choices',
        ]);

        /*
         * Grade release and answer release are treated
         * separately.
         *
         * Change this to Assignment::answersReleased()
         * if your system has a separate answer-release
         * setting.
         */
        $answersReleased =
            (bool) ($submission->grade?->is_released ?? false);

        return Inertia::render(
            'student/SubmissionResult',
            [
                'submission' =>
                    $this->transformSubmission(
                        $submission,
                        $answersReleased
                    ),

                'answersReleased' =>
                    $answersReleased,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Assignment
    |--------------------------------------------------------------------------
    */

    private function resolveAssignment(
        string $id
    ): Assignment {
        try {
            $assignmentId =
                Crypt::decryptString($id);

            return Assignment::findOrFail(
                (int) $assignmentId
            );
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Section
    |--------------------------------------------------------------------------
    */

    private function resolveSection(
        string $id
    ): Section {
        try {
            $sectionId =
                Crypt::decryptString($id);

            return Section::findOrFail(
                (int) $sectionId
            );
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Submission
    |--------------------------------------------------------------------------
    */

    private function resolveSubmission(
        string $id
    ): Submission {
        try {
            $submissionId =
                Crypt::decryptString($id);

            return Submission::findOrFail(
                (int) $submissionId
            );
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Assignment
    |--------------------------------------------------------------------------
    */

    private function transformAssignmentForStudent(
        Assignment $assignment
    ): array {
        return [
            'id' =>
                $this->encryptId(
                    $assignment->id
                ),

            'title' =>
                $assignment->title,

            'type' =>
                $assignment->type,

            'instructions' =>
                $assignment->instructions,

            'max_score' =>
                $assignment->max_score,

            'due_date' =>
                $assignment
                    ->due_date
                    ?->toIso8601String(),

            'language' =>
                $assignment->language,

            'section' =>
                $assignment->section
                    ? [
                        'id' =>
                            $this->encryptId(
                                $assignment
                                    ->section
                                    ->id
                            ),

                        'name' =>
                            $assignment
                                ->section
                                ->name,

                        'subject' =>
                            $assignment
                                ->section
                                ->subject
                                ? [
                                    'code' =>
                                        $assignment
                                            ->section
                                            ->subject
                                            ->code,
                                ]
                                : null,
                    ]
                    : null,

            'questions' =>
                $assignment
                    ->questions
                    ->map(
                        fn ($question) => [
                            'id' =>
                                $this->encryptId(
                                    $question->id
                                ),

                            'question' =>
                                $question->question,

                            'points' =>
                                $question->points,

                            'choices' =>
                                $question
                                    ->choices
                                    ->map(
                                        fn ($choice) => [
                                            'id' =>
                                                $this->encryptId(
                                                    $choice->id
                                                ),

                                            'choice_text' =>
                                                $choice->choice_text,
                                        ]
                                    )
                                    ->values()
                                    ->all(),
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Existing Submission
    |--------------------------------------------------------------------------
    */

    private function transformExistingSubmission(
        Submission $submission
    ): array {
        $answers =
            $submission->answers ?? [];

        return [
            'id' =>
                $this->encryptId(
                    $submission->id
                ),

            'content' =>
                $submission->content,

            'answers' =>
                collect($answers)
                    ->mapWithKeys(
                        function (
                            $choiceId,
                            $questionId
                        ) {
                            return [
                                $this->encryptId(
                                    $questionId
                                ) =>
                                    $choiceId !== null
                                        ? $this->encryptId(
                                            $choiceId
                                        )
                                        : null,
                            ];
                        }
                    )
                    ->all(),

            'status' =>
                $submission->status,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Submission Result
    |--------------------------------------------------------------------------
    */

    private function transformSubmission(
        Submission $submission,
        bool $answersReleased
    ): array {
        $answers =
            $submission->answers ?? [];

        return [
            'id' =>
                $this->encryptId(
                    $submission->id
                ),

            'status' =>
                $submission->status,

            'content' =>
                $submission->content,

            'answers' =>
                collect($answers)
                    ->mapWithKeys(
                        function (
                            $choiceId,
                            $questionId
                        ) {
                            return [
                                $this->encryptId(
                                    $questionId
                                ) =>
                                    $choiceId !== null
                                        ? $this->encryptId(
                                            $choiceId
                                        )
                                        : null,
                            ];
                        }
                    )
                    ->all(),

            'submitted_at' =>
                $submission
                    ->submitted_at
                    ?->toIso8601String(),

            'started_at' =>
                $submission
                    ->started_at
                    ?->toIso8601String(),

            'expires_at' =>
                $submission
                    ->expires_at
                    ?->toIso8601String(),

            'student' =>
                $submission->student
                    ? [
                        'id' =>
                            $this->encryptId(
                                $submission
                                    ->student
                                    ->id
                            ),

                        'student_no' =>
                            $submission
                                ->student
                                ->student_no,

                        'first_name' =>
                            $submission
                                ->student
                                ->first_name,

                        'last_name' =>
                            $submission
                                ->student
                                ->last_name,
                    ]
                    : null,

            'assignment' =>
                $submission->assignment
                    ? [
                        'id' =>
                            $this->encryptId(
                                $submission
                                    ->assignment
                                    ->id
                            ),

                        'title' =>
                            $submission
                                ->assignment
                                ->title,

                        'type' =>
                            $submission
                                ->assignment
                                ->type,

                        'max_score' =>
                            $submission
                                ->assignment
                                ->max_score,

                        'section' =>
                            $submission
                                ->assignment
                                ->section
                                ? [
                                    'id' =>
                                        $this->encryptId(
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
             * IMPORTANT:
             *
             * Questions are ALWAYS returned.
             *
             * answersReleased only controls whether
             * is_correct is exposed.
             */
            'questions' =>
                collect(
                    $submission
                        ->assignment
                        ?->questions ?? []
                )
                    ->map(
                        function ($question) use (
                            $answers,
                            $answersReleased
                        ) {
                            $selectedChoiceId =
                                $answers[
                                    $question->id
                                ] ?? null;

                            return [
                                'id' =>
                                    $this->encryptId(
                                        $question->id
                                    ),

                                'question' =>
                                    $question->question,

                                'points' =>
                                    $question->points,

                                'selected_choice_id' =>
                                    $selectedChoiceId !== null
                                        ? $this->encryptId(
                                            $selectedChoiceId
                                        )
                                        : null,

                                'choices' =>
                                    collect(
                                        $question->choices ?? []
                                    )
                                        ->map(
                                            function (
                                                $choice
                                            ) use (
                                                $selectedChoiceId,
                                                $answersReleased
                                            ) {
                                                return [
                                                    'id' =>
                                                        $this->encryptId(
                                                            $choice->id
                                                        ),

                                                    'choice_text' =>
                                                        $choice
                                                            ->choice_text,

                                                    /*
                                                     * Do not expose
                                                     * correct-answer
                                                     * information until
                                                     * answers are released.
                                                     */
                                                    'is_correct' =>
                                                        $answersReleased
                                                            ? (bool) $choice->is_correct
                                                            : null,

                                                    'is_selected' =>
                                                        $selectedChoiceId !== null &&
                                                        (int) $selectedChoiceId ===
                                                        (int) $choice->id,
                                                ];
                                            }
                                        )
                                        ->values()
                                        ->all(),
                            ];
                        }
                    )
                    ->values()
                    ->all(),

            'grade' =>
                $submission->grade
                    ? $this->transformGrade(
                        $submission->grade
                    )
                    : null,

            'ai_feedback' =>
                $submission->aiFeedback
                    ? [
                        'id' =>
                            $this->encryptId(
                                $submission
                                    ->aiFeedback
                                    ->id
                            ),

                        'score' =>
                            $submission
                                ->aiFeedback
                                ->score,

                        'feedback' =>
                            $submission
                                ->aiFeedback
                                ->feedback_json,
                    ]
                    : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Submission Summary
    |--------------------------------------------------------------------------
    */

    private function transformSubmissionSummary(
        Submission $submission
    ): array {
        return [
            'id' =>
                $this->encryptId(
                    $submission->id
                ),

            'status' =>
                $submission->status,

            'submitted_at' =>
                $submission
                    ->submitted_at
                    ?->toIso8601String(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Section
    |--------------------------------------------------------------------------
    */

    private function transformSection(
        Section $section
    ): array {
        return [
            'id' =>
                $this->encryptId(
                    $section->id
                ),

            'name' =>
                $section->name,

            'schedule' =>
                $section->schedule,

            'subject' =>
                $section->subject
                    ? [
                        'id' =>
                            $this->encryptId(
                                $section
                                    ->subject
                                    ->id
                            ),

                        'code' =>
                            $section
                                ->subject
                                ->code,

                        'name' =>
                            $section
                                ->subject
                                ->name,
                    ]
                    : null,

            'semester' =>
                $section->semester
                    ? [
                        'id' =>
                            $this->encryptId(
                                $section
                                    ->semester
                                    ->id
                            ),

                        'name' =>
                            $section
                                ->semester
                                ->name,

                        'school_year' =>
                            $section
                                ->semester
                                ->school_year,
                    ]
                    : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Grade
    |--------------------------------------------------------------------------
    */

    private function transformGrade(
        Grade $grade
    ): array {
        return [
            'id' =>
                $this->encryptId(
                    $grade->id
                ),

            'raw_score' =>
                $grade->raw_score,

            'max_score' =>
                $grade->max_score,

            'percentage' =>
                $grade->percentage,

            'remarks' =>
                $grade->remarks,

            'is_released' =>
                (bool) $grade->is_released,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Ensure Student Enrollment
    |--------------------------------------------------------------------------
    */

    private function ensureStudentEnrolled(
        int $studentId,
        int $sectionId
    ): void {
        $enrolled = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('section_id', $sectionId)
            ->where('status', 'active')
            ->exists();

        abort_unless(
            $enrolled,
            403
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Encrypt ID
    |--------------------------------------------------------------------------
    */

    private function encryptId(
        int|string $id
    ): string {
        return Crypt::encryptString(
            (string) $id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Decrypt ID
    |--------------------------------------------------------------------------
    */

    private function decryptId(
        string $id
    ): int {
        try {
            return (int) Crypt::decryptString($id);
        } catch (\Throwable $e) {
            abort(
                422,
                'Invalid encrypted ID.'
            );
        }
    }
}