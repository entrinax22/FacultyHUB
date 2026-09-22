<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentQuestion;
use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\Section;
use App\Models\Module;
use App\Services\AIGraderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Smalot\PdfParser\Parser;

class AssignmentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Resolvers
    |--------------------------------------------------------------------------
    */

    private function resolveSection(string $id): Section
    {
        try {
            $sectionId = Crypt::decryptString($id);

            return Section::findOrFail($sectionId);
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    private function resolveAssignment(string $id): Assignment
    {
        try {
            $assignmentId = Crypt::decryptString($id);

            return Assignment::findOrFail($assignmentId);
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    private function resolveModule(string $id): Module
    {
        try {
            $moduleId = Crypt::decryptString($id);

            return Module::findOrFail($moduleId);
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    private function resolveGradingComponent(string $id): GradingComponent
    {
        try {
            $componentId = Crypt::decryptString($id);

            return GradingComponent::findOrFail($componentId);
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    private function paginationData($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->nextPageUrl(),
            'previous_page_url' => $paginator->previousPageUrl(),
            'first_page_url' => $paginator->url(1),
            'last_page_url' => $paginator->url(
                $paginator->lastPage()
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function index(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        return Inertia::render('assignments/Index', [
            'section' => $this->transformSection($section),
        ]);
    }

    public function create(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        return Inertia::render('assignments/Form', [
            'section' => $this->transformSection($section),
            'modules' => $this->getSectionModules($section),
            'components' => $this->getSectionComponents($section),
        ]);
    }

    public function show(string $id): Response
    {
        $assignment = $this->resolveAssignment($id);

        $assignment->load([
            'section.subject',
            'section.semester',
            'questions.choices',
            'module',
        ]);

        $assignment->loadCount('submissions');

        return Inertia::render('assignments/Show', [
            'assignment' => $this->transformAssignment(
                $assignment,
                true
            ),
            'plagiarismRan' => $assignment
                ->plagiarismReports()
                ->exists(),
        ]);
    }

    public function edit(string $id): Response
    {
        $assignment = $this->resolveAssignment($id);

        $assignment->load([
            'section.subject',
            'section.semester',
            'questions.choices',
            'module',
            'gradingComponent',
        ]);

        $section = $assignment->section;

        return Inertia::render('assignments/Form', [
            'assignment' => $this->transformAssignment(
                $assignment,
                true
            ),

            'section' => $this->transformSection($section),

            'modules' => $this->getSectionModules($section),

            'components' => $this->getSectionComponents($section),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment Data
    |--------------------------------------------------------------------------
    */

    public function data(
        Request $request,
        string $sectionId
    ): JsonResponse {
        try {
            $section = $this->resolveSection($sectionId);

            $query = $section->assignments()
                ->withCount('submissions');

            if ($search = $request->get('search')) {
                $query->where(
                    'title',
                    'like',
                    "%{$search}%"
                );
            }

            $perPage = min(
                max(
                    $request->integer('per_page', 20),
                    1
                ),
                100
            );

            $page = max(
                $request->integer('page', 1),
                1
            );

            $assignments = $query
                ->latest('created_at')
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            $assignments->through(
                fn (Assignment $assignment) =>
                    $this->transformAssignmentList($assignment)
            );

            return response()->json([
                'success' => true,
                'message' => 'Assignments retrieved successfully.',
                'data' => $assignments->items(),
                'pagination' => $this->paginationData($assignments),
                'filters' => [
                    'search' => $request->get('search'),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assignments.',
                'data' => [],
                'pagination' => null,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Submission Data
    |--------------------------------------------------------------------------
    */

    public function submissionsData(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $assignment = $this->resolveAssignment($id);

            $query = $assignment->submissions()
                ->withCount([
                    'proctoringEvents as proctoring_events_count',

                    'proctoringEvents as proctoring_alerts_count' =>
                        function ($query) {
                            $query->whereIn('event_type', [
                                'tab_hidden',
                                'window_resized',
                                'fullscreen_exited',
                                'copy_detected',
                                'paste_detected',
                                'cut_detected',
                                'context_menu_used',
                                'print_screen_suspected',
                                'camera_permission_denied',
                            ]);
                        },
                ])
                ->with([
                    'student',
                    'grade',
                    'aiFeedback',
                    'proctoringEvents' => function ($query) {
                        $query->latest();
                    },
                ]);

            if ($search = $request->get('search')) {
                $query->whereHas(
                    'student',
                    function ($studentQuery) use ($search) {
                        $studentQuery
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            }

            $perPage = min(
                max(
                    $request->integer('per_page', 20),
                    1
                ),
                100
            );

            $page = max(
                $request->integer('page', 1),
                1
            );

            $submissions = $query
                ->latest('submitted_at')
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            $submissions->through(
                fn ($submission) =>
                    $this->transformSubmission($submission)
            );

            return response()->json([
                'success' => true,
                'message' => 'Submissions retrieved successfully.',
                'data' => $submissions->items(),
                'pagination' => $this->paginationData($submissions),
                'filters' => [
                    'search' => $request->get('search'),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve submissions.',
                'data' => [],
                'pagination' => null,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        string $sectionId
    ): JsonResponse {
        $section = $this->resolveSection($sectionId);

        if (! $section->gradingComponents()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Please set up your grading components before creating an assignment.',
            ], 422);
        }

        $validated = $this->validateAssignment(
            $request,
            $section
        );

        $assignment = DB::transaction(
            function () use (
                $request,
                $section,
                $validated
            ) {
                $assignment = $section
                    ->assignments()
                    ->create($validated);

                if ($assignment->type === 'mcq') {
                    $this->syncQuestions(
                        $assignment,
                        $request->input(
                            'questions',
                            []
                        )
                    );
                }

                $this->syncGradingItem($assignment);

                return $assignment;
            }
        );

        $assignment->load([
            'section.subject',
            'section.semester',
            'questions.choices',
            'module',
            'gradingComponent',
        ]);

        $assignment->loadCount('submissions');

        return response()->json([
            'success' => true,
            'message' => 'Assignment created successfully.',
            'data' => $this->transformAssignment(
                $assignment,
                true
            ),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $assignment = $this->resolveAssignment($id);

        $assignment->load('section');

        $validated = $this->validateAssignment(
            $request,
            $assignment->section
        );

        DB::transaction(
            function () use (
                $request,
                $assignment,
                $validated
            ) {
                $assignment->update($validated);

                if ($assignment->type === 'mcq') {
                    $this->syncQuestions(
                        $assignment,
                        $request->input(
                            'questions',
                            []
                        )
                    );
                } else {
                    $assignment->questions()->delete();
                }

                $this->syncGradingItem($assignment);
            }
        );

        $assignment->load([
            'section.subject',
            'section.semester',
            'questions.choices',
            'module',
            'gradingComponent',
        ]);

        $assignment->loadCount('submissions');

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully.',
            'data' => $this->transformAssignment(
                $assignment,
                true
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id): JsonResponse
    {
        $assignment = $this->resolveAssignment($id);

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Publish
    |--------------------------------------------------------------------------
    */

    public function togglePublish(string $id): JsonResponse
    {
        $assignment = $this->resolveAssignment($id);

        $assignment->update([
            'is_published' => ! $assignment->is_published,
        ]);

        return response()->json([
            'success' => true,
            'message' => $assignment->is_published
                ? 'Assignment published.'
                : 'Assignment set to draft.',
            'data' => [
                'is_published' =>
                    (bool) $assignment->is_published,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Import PDF
    |--------------------------------------------------------------------------
    */

    public function importPdf(
        Request $request,
        string $sectionId,
        Parser $parser,
        AIGraderService $ai
    ): JsonResponse {
        $this->resolveSection($sectionId);

        $validated = $request->validate([
            'document' => [
                'required',
                'file',
                'mimes:pdf',
                'max:10240',
            ],
        ]);

        try {
            $document = $parser->parseFile(
                $validated['document']->getRealPath()
            );

            $text = trim(
                $document->getText()
            );

            if ($text === '') {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'The PDF does not contain readable text.',
                ], 422);
            }

            if (mb_strlen($text) > 120000) {
                $text = mb_substr(
                    $text,
                    0,
                    120000
                );
            }

            $draft = $ai->createAssignmentDraft($text);

            if (isset($draft['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $draft['error'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Assignment draft generated successfully.',
                'data' => $this->transformImportedDraft($draft),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'The PDF could not be processed. Please check the file and try again.',
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    private function validateAssignment(
        Request $request,
        Section $section
    ): array {
        $request->merge([
            'proctoring_enabled' =>
                $request->boolean(
                    'proctoring_enabled'
                ),
        ]);

        $validated = $request->validate([
            'title' =>
                'required|string|max:255',

            'instructions' =>
                'required|string',

            'type' =>
                'required|in:essay,mcq,code',

            'period' =>
                'nullable|in:midterm,finals',

            'category' =>
                'nullable|in:quiz,exam,activity,project',

            'component_id' =>
                'nullable|string',

            'due_date' =>
                'nullable|date',

            'max_score' =>
                'required|numeric|min:1',

            'passing_score' =>
                'nullable|numeric|min:0',

            'is_published' =>
                'boolean',

            'rubric' =>
                'nullable|string',

            'language' =>
                'nullable|string|max:30',

            'answer_release_at' =>
                'nullable|date',

            'module_id' =>
                'nullable|string',

            'duration_minutes' =>
                'nullable|integer|min:1|max:600',

            'proctoring_enabled' =>
                ['required', 'boolean'],

            'questions' =>
                'nullable|array',

            'questions.*.id' =>
                'nullable|string',

            'questions.*.question' =>
                'required_if:type,mcq|string',

            'questions.*.points' =>
                'nullable|numeric|min:0.5',

            'questions.*.choices' =>
                'required_if:type,mcq|array|min:2',

            'questions.*.choices.*.id' =>
                'nullable|string',

            'questions.*.choices.*.choice_text' =>
                'required|string',

            'questions.*.choices.*.is_correct' =>
                'boolean',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Component
        |--------------------------------------------------------------------------
        */

        if (! empty($validated['component_id'])) {
            try {
                $componentId = Crypt::decryptString(
                    $validated['component_id']
                );

                $component = $section
                    ->gradingComponents()
                    ->findOrFail($componentId);

                $validated['component_id'] =
                    $component->id;
            } catch (\Throwable $e) {
                abort(404);
            }
        } else {
            $validated['component_id'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Module
        |--------------------------------------------------------------------------
        */

        if (! empty($validated['module_id'])) {
            try {
                $moduleId = Crypt::decryptString(
                    $validated['module_id']
                );

                $module = $section
                    ->modules()
                    ->findOrFail($moduleId);

                $validated['module_id'] =
                    $module->id;
            } catch (\Throwable $e) {
                abort(404);
            }
        } else {
            $validated['module_id'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | MCQ Validation
        |--------------------------------------------------------------------------
        */

        if ($validated['type'] === 'mcq') {
            $questions = $validated['questions'] ?? [];

            if (empty($questions)) {
                validator()->make(
                    [],
                    [
                        'questions' =>
                            'required|array|min:1',
                    ]
                )->validate();
            }

            foreach ($questions as $question) {
                $hasCorrectChoice = collect(
                    $question['choices'] ?? []
                )->contains(
                    fn ($choice) =>
                        ! empty($choice['is_correct'])
                );

                if (! $hasCorrectChoice) {
                    validator()->make(
                        [],
                        [
                            'choices' =>
                                'required',
                        ]
                    )->errors()->add(
                        'questions',
                        'Each multiple-choice question must have at least one correct answer.'
                    );

                    abort(
                        response()->json([
                            'success' => false,
                            'message' =>
                                'Each multiple-choice question must have at least one correct answer.',
                        ], 422)
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Timed Assignment
        |--------------------------------------------------------------------------
        */

        $isTimedAssignment =
            ($validated['category'] ?? null) === 'exam'
            ||
            (
                ($validated['type'] ?? null) === 'mcq'
                &&
                ! empty(
                    $validated['proctoring_enabled']
                )
            );

        if (
            $isTimedAssignment
            &&
            empty(
                $validated['duration_minutes']
            )
        ) {
            validator()->make(
                $validated,
                [
                    'duration_minutes' =>
                        'required|integer|min:1|max:600',
                ]
            )->validate();
        }

        return $validated;
    }

    /*
    |--------------------------------------------------------------------------
    | Grading Item
    |--------------------------------------------------------------------------
    */

    private function syncGradingItem(
        Assignment $assignment
    ): void {
        if (! $assignment->component_id) {
            GradingItem::where(
                'assignment_id',
                $assignment->id
            )->delete();

            return;
        }

        $existing = GradingItem::where(
            'assignment_id',
            $assignment->id
        )->first();

        if ($existing) {
            $existing->update([
                'component_id' =>
                    $assignment->component_id,

                'name' =>
                    $assignment->title,

                'max_score' =>
                    $assignment->max_score,
            ]);

            return;
        }

        $order = GradingItem::where(
            'section_id',
            $assignment->section_id
        )
            ->where(
                'component_id',
                $assignment->component_id
            )
            ->max('order');

        GradingItem::create([
            'section_id' =>
                $assignment->section_id,

            'component_id' =>
                $assignment->component_id,

            'assignment_id' =>
                $assignment->id,

            'name' =>
                $assignment->title,

            'max_score' =>
                $assignment->max_score,

            'order' =>
                ($order ?? 0) + 1,

            'is_enabled' =>
                true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Questions
    |--------------------------------------------------------------------------
    */

    private function syncQuestions(
        Assignment $assignment,
        array $questions
    ): void {
        $existingIds = [];

        foreach ($questions as $order => $qData) {
            $question = null;

            if (! empty($qData['id'])) {
                try {
                    $questionId =
                        Crypt::decryptString(
                            $qData['id']
                        );

                    $question = $assignment
                        ->questions()
                        ->find($questionId);
                } catch (\Throwable $e) {
                    $question = null;
                }
            }

            if ($question) {
                $question->update([
                    'question' =>
                        $qData['question'],

                    'order' =>
                        $order,

                    'points' =>
                        $qData['points'] ?? 1,
                ]);
            } else {
                $question = $assignment
                    ->questions()
                    ->create([
                        'question' =>
                            $qData['question'],

                        'order' =>
                            $order,

                        'points' =>
                            $qData['points'] ?? 1,
                    ]);
            }

            $existingIds[] = $question->id;

            $this->syncChoices(
                $question,
                $qData['choices'] ?? []
            );
        }

        if (empty($existingIds)) {
            $assignment
                ->questions()
                ->delete();

            return;
        }

        $assignment
            ->questions()
            ->whereNotIn(
                'id',
                $existingIds
            )
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Choices
    |--------------------------------------------------------------------------
    */

    private function syncChoices(
        AssignmentQuestion $question,
        array $choices
    ): void {
        $existingIds = [];

        foreach ($choices as $order => $cData) {
            $choice = null;

            if (! empty($cData['id'])) {
                try {
                    $choiceId =
                        Crypt::decryptString(
                            $cData['id']
                        );

                    $choice = $question
                        ->choices()
                        ->find($choiceId);
                } catch (\Throwable $e) {
                    $choice = null;
                }
            }

            if ($choice) {
                $choice->update([
                    'choice_text' =>
                        $cData['choice_text'],

                    'is_correct' =>
                        (bool) (
                            $cData['is_correct']
                            ?? false
                        ),

                    'order' =>
                        $order,
                ]);
            } else {
                $choice = $question
                    ->choices()
                    ->create([
                        'choice_text' =>
                            $cData['choice_text'],

                        'is_correct' =>
                            (bool) (
                                $cData['is_correct']
                                ?? false
                            ),

                        'order' =>
                            $order,
                    ]);
            }

            $existingIds[] = $choice->id;
        }

        if (empty($existingIds)) {
            $question
                ->choices()
                ->delete();

            return;
        }

        $question
            ->choices()
            ->whereNotIn(
                'id',
                $existingIds
            )
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Section Options
    |--------------------------------------------------------------------------
    */

    private function getSectionModules(
        Section $section
    ): array {
        return $section
            ->modules()
            ->where('is_published', true)
            ->orderBy('order')
            ->get([
                'id',
                'title',
            ])
            ->map(
                fn (Module $module) => [
                    'id' => Crypt::encryptString(
                        (string) $module->id
                    ),
                    'title' => $module->title,
                ]
            )
            ->values()
            ->all();
    }

    private function getSectionComponents(
        Section $section
    ): array {
        return $section
            ->gradingComponents()
            ->orderBy('order')
            ->get([
                'id',
                'name',
                'period',
                'weight_percentage',
            ])
            ->map(
                fn (
                    GradingComponent $component
                ) => [
                    'id' => Crypt::encryptString(
                        (string) $component->id
                    ),
                    'name' => $component->name,
                    'period' => $component->period,
                    'weight_percentage' =>
                        $component->weight_percentage,
                ]
            )
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Transformers
    |--------------------------------------------------------------------------
    */

    private function transformAssignmentList(
        Assignment $assignment
    ): array {
        return [
            'id' => Crypt::encryptString(
                (string) $assignment->id
            ),

            'title' =>
                $assignment->title,

            'type' =>
                $assignment->type,

            'period' =>
                $assignment->period,

            'category' =>
                $assignment->category,

            'due_date' =>
                $assignment->due_date,

            'max_score' =>
                $assignment->max_score,

            'is_published' =>
                (bool) $assignment->is_published,

            'submissions_count' =>
                $assignment->submissions_count ?? 0,
        ];
    }

    private function transformSection(
        Section $section
    ): array {
        return [
            'id' => Crypt::encryptString(
                (string) $section->id
            ),

            'name' =>
                $section->name,

            'schedule' =>
                $section->schedule,

            'subject' => $section->subject
                ? [
                    'id' => Crypt::encryptString(
                        (string) $section->subject->id
                    ),
                    'code' =>
                        $section->subject->code,
                    'name' =>
                        $section->subject->name,
                ]
                : null,

            'semester' => $section->semester
                ? [
                    'id' => Crypt::encryptString(
                        (string) $section->semester->id
                    ),
                    'name' =>
                        $section->semester->name,
                    'school_year' =>
                        $section->semester->school_year,
                ]
                : null,
        ];
    }

    private function transformAssignment(
        Assignment $assignment,
        bool $includeQuestions = false
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Component
        |--------------------------------------------------------------------------
        |
        | Encrypt the grading component ID ONCE.
        | Because Crypt::encryptString() uses a random IV, encrypting the same
        | database ID twice produces different ciphertext.
        |
        */

        $encryptedComponentId = null;

        if (
            $assignment->relationLoaded('gradingComponent')
            && $assignment->gradingComponent
        ) {
            $encryptedComponentId = Crypt::encryptString(
                (string) $assignment->gradingComponent->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Module
        |--------------------------------------------------------------------------
        */

        $encryptedModuleId = null;

        if (
            $assignment->relationLoaded('module')
            && $assignment->module
        ) {
            $encryptedModuleId = Crypt::encryptString(
                (string) $assignment->module->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Base Assignment Data
        |--------------------------------------------------------------------------
        */

        $data = [
            'id' => Crypt::encryptString(
                (string) $assignment->id
            ),

            'title' =>
                $assignment->title,

            'instructions' =>
                $assignment->instructions,

            'type' =>
                $assignment->type,

            'period' =>
                $assignment->period,

            'category' =>
                $assignment->category,

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            | This is the value used by the Assignment Form.
            */

            'component_id' =>
                $encryptedComponentId,

            /*
            |--------------------------------------------------------------------------
            | Component display information
            |--------------------------------------------------------------------------
            */

            'grading_component' =>
                $assignment->relationLoaded('gradingComponent')
                && $assignment->gradingComponent
                    ? [
                        'id' =>
                            $encryptedComponentId,

                        'name' =>
                            $assignment->gradingComponent->name,

                        'period' =>
                            $assignment->gradingComponent->period,

                        'weight_percentage' =>
                            $assignment->gradingComponent->weight_percentage,
                    ]
                    : null,

            'due_date' =>
                $assignment->due_date,

            'max_score' =>
                $assignment->max_score,

            'passing_score' =>
                $assignment->passing_score,

            'is_published' =>
                (bool) $assignment->is_published,

            'rubric' =>
                $assignment->rubric,

            'language' =>
                $assignment->language,

            'answer_release_at' =>
                $assignment->answer_release_at,

            'duration_minutes' =>
                $assignment->duration_minutes,

            'proctoring_enabled' =>
                (bool) $assignment->proctoring_enabled,

            'submissions_count' =>
                $assignment->submissions_count ?? 0,

            /*
            |--------------------------------------------------------------------------
            | Section
            |--------------------------------------------------------------------------
            */

            'section' =>
                $assignment->relationLoaded('section')
                && $assignment->section
                    ? $this->transformSection(
                        $assignment->section
                    )
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Module
            |--------------------------------------------------------------------------
            */

            'module' =>
                $assignment->relationLoaded('module')
                && $assignment->module
                    ? [
                        'id' =>
                            $encryptedModuleId,

                        'title' =>
                            $assignment->module->title,
                    ]
                    : null,
        ];

        /*
        |--------------------------------------------------------------------------
        | Questions
        |--------------------------------------------------------------------------
        */

        if ($includeQuestions) {
            $data['questions'] =
                $assignment->questions
                    ->map(
                        fn ($question) =>
                            $this->transformQuestion(
                                $question
                            )
                    )
                    ->values()
                    ->all();
        }

        return $data;
    }

    private function transformQuestion(
        AssignmentQuestion $question
    ): array {
        return [
            'id' => Crypt::encryptString(
                (string) $question->id
            ),

            'question' =>
                $question->question,

            'order' =>
                $question->order,

            'points' =>
                $question->points,

            'choices' =>
                $question->choices
                    ->map(
                        fn ($choice) => [
                            'id' =>
                                Crypt::encryptString(
                                    (string) $choice->id
                                ),

                            'choice_text' =>
                                $choice->choice_text,

                            'is_correct' =>
                                (bool) $choice->is_correct,

                            'order' =>
                                $choice->order,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function transformSubmission(
        $submission
    ): array {
        return [
            'id' => Crypt::encryptString(
                (string) $submission->id
            ),

            'student' => $submission->student
                ? [
                    'id' => Crypt::encryptString(
                        (string) $submission
                            ->student
                            ->id
                    ),

                    'first_name' =>
                        $submission
                            ->student
                            ->first_name,
                    
                    'last_name' =>
                        $submission
                            ->student
                            ->last_name,

                    'email' =>
                        $submission
                            ->student
                            ->email,
                ]
                : null,
           
            'status' =>
                $submission->status,

            'submitted_at' =>
                $submission->submitted_at,

            'grade' => $submission->grade
                ? [
                    'id' => Crypt::encryptString(
                        (string) $submission
                            ->grade
                            ->id
                    ),

                    'raw_score' =>
                        $submission
                            ->grade
                            ->raw_score,

                    'max_score' =>
                        $submission
                            ->grade
                            ->max_score,

                    'percentage' =>
                        $submission
                            ->grade
                            ->percentage,

                    'status' =>
                        $submission
                            ->grade
                            ->status,

                    'remarks' =>
                        $submission
                            ->grade
                            ->remarks,
                ]
                : null,

            'ai_feedback' =>
                $submission->aiFeedback
                    ? [
                        'id' => Crypt::encryptString(
                            (string) $submission->aiFeedback->id
                        ),

                        'score' =>
                            $submission->aiFeedback->score,

                        'feedback' =>
                            $submission->aiFeedback->feedback,
                    ]
                    : null,
            /*
            |--------------------------------------------------------------------------
            | AI Score
            |--------------------------------------------------------------------------
            */

            'ai_score' =>
                $submission->aiFeedback?->score,
            
            /*
            |--------------------------------------------------------------------------
            | Proctoring
            |--------------------------------------------------------------------------
            */

            'proctoring_events_count' =>
                $submission
                    ->proctoring_events_count
                    ?? 0,

            'proctoring_alerts_count' =>
                $submission
                    ->proctoring_alerts_count
                    ?? 0,

            'proctoring_events' =>
                $submission
                    ->proctoringEvents
                    ->map(
                        fn ($event) => [
                            'id' =>
                                Crypt::encryptString(
                                    (string) $event->id
                                ),

                            'event_type' =>
                                $event->event_type,

                            'description' =>
                                $event->description,

                            'created_at' =>
                                $event->created_at,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Imported Assignment Draft
    |--------------------------------------------------------------------------
    */

    private function transformImportedDraft(
        array $draft
    ): array {
        return [
            'title' =>
                (string) (
                    $draft['title'] ?? ''
                ),

            'type' =>
                in_array(
                    $draft['type'] ?? null,
                    [
                        'essay',
                        'mcq',
                        'code',
                    ],
                    true
                )
                    ? $draft['type']
                    : 'essay',

            'category' =>
                in_array(
                    $draft['category'] ?? null,
                    [
                        'quiz',
                        'exam',
                        'activity',
                        'project',
                    ],
                    true
                )
                    ? $draft['category']
                    : null,

            'language' =>
                in_array(
                    $draft['language'] ?? null,
                    [
                        'python',
                        'javascript',
                        'java',
                        'cpp',
                        'csharp',
                        'php',
                        'ruby',
                        'go',
                    ],
                    true
                )
                    ? $draft['language']
                    : null,

            'instructions' =>
                (string) (
                    $draft['instructions'] ?? ''
                ),

            'rubric' =>
                (string) (
                    $draft['rubric'] ?? ''
                ),

            'questions' =>
                collect(
                    $draft['questions'] ?? []
                )
                    ->filter(
                        fn ($question) =>
                            filled(
                                $question['question']
                                ?? null
                            )
                    )
                    ->map(
                        fn ($question) => [
                            'question' =>
                                (string)
                                $question['question'],

                            'points' =>
                                max(
                                    0.5,
                                    (float) (
                                        $question['points']
                                        ?? 1
                                    )
                                ),

                            'choices' =>
                                collect(
                                    $question['choices']
                                    ?? []
                                )
                                    ->filter(
                                        fn ($choice) =>
                                            filled(
                                                $choice[
                                                    'choice_text'
                                                ] ?? null
                                            )
                                    )
                                    ->map(
                                        fn ($choice) => [
                                            'choice_text' =>
                                                (string)
                                                $choice[
                                                    'choice_text'
                                                ],

                                            'is_correct' =>
                                                (bool) (
                                                    $choice[
                                                        'is_correct'
                                                    ] ?? false
                                                ),
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
}