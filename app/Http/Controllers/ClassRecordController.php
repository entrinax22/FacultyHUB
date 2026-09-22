<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\GradingItemScore;
use App\Models\Section;
use App\Models\TransmutationScale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class ClassRecordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Class Record Page
    |--------------------------------------------------------------------------
    */

    public function page(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
            'faculty',
        ]);

        return Inertia::render('class-record/Index', [
            'section' => [
                'id' => $this->encryptId($section->id),

                'name' => $section->name,

                'subject' => $section->subject
                    ? [
                        'id' => $this->encryptId(
                            $section->subject->id
                        ),
                        'code' => $section->subject->code,
                        'name' => $section->subject->name,
                    ]
                    : null,

                'semester' => $section->semester
                    ? [
                        'name' => $section->semester->name,
                        'school_year' => $section->semester->school_year,
                    ]
                    : null,

                'faculty' => $section->faculty
                    ? [
                        'name' => $section->faculty->name,
                    ]
                    : null,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Class Record Data
    |--------------------------------------------------------------------------
    */

    public function show(string $sectionId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Class record loaded successfully.',
            'data' => $this->getClassRecordData($sectionId),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Export Class Record PDF
    |--------------------------------------------------------------------------
    */

    public function exportPdf(string $sectionId)
    {
        $data = $this->getClassRecordData($sectionId);

        $pdf = Pdf::loadView(
            'pdf.class-record',
            $data
        );

        $pdf->setPaper(
            'a4',
            'landscape'
        );

        $sectionName = $data['section']['name']
            ?? 'class-record';

        $safeSectionName = preg_replace(
            '/[^A-Za-z0-9\-_]+/',
            '-',
            $sectionName
        );

        $fileName =
            'class-record-' .
            trim($safeSectionName, '-') .
            '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | Build Complete Class Record Data
    |--------------------------------------------------------------------------
    |
    | This method is shared by:
    |
    | - show()
    | - exportPdf()
    |
    | Keeping the calculations in one place ensures that the class record
    | displayed in Vue and the exported PDF use the same data.
    |
    */

    private function getClassRecordData(
        string $sectionId
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Resolve Section
        |--------------------------------------------------------------------------
        */

        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
            'faculty',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Grading Components
        |--------------------------------------------------------------------------
        */

        $components = $section
            ->gradingComponents()
            ->orderBy('order')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Grading Items
        |--------------------------------------------------------------------------
        */

        $items = GradingItem::query()
            ->where('section_id', $section->id)
            ->where('is_enabled', true)
            ->orderBy('component_id')
            ->orderBy('order')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Published Assignments
        |--------------------------------------------------------------------------
        */

        $assignments = Assignment::query()
            ->where('section_id', $section->id)
            ->where('is_published', true)
            ->orderBy('created_at')
            ->get([
                'id',
                'title',
                'max_score',
                'type',
                'period',
                'category',
                'component_id',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Encrypted ID Maps
        |--------------------------------------------------------------------------
        |
        | Crypt::encryptString() produces a different ciphertext each time.
        | Therefore each database ID is encrypted once and reused throughout
        | this response.
        |
        */

        $componentIds = $components->mapWithKeys(
            fn ($component) => [
                $component->id => $this->encryptId(
                    $component->id
                ),
            ]
        );

        $itemIds = $items->mapWithKeys(
            fn ($item) => [
                $item->id => $this->encryptId(
                    $item->id
                ),
            ]
        );

        $assignmentIds = $assignments->mapWithKeys(
            fn ($assignment) => [
                $assignment->id => $this->encryptId(
                    $assignment->id
                ),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Enrollments
        |--------------------------------------------------------------------------
        */

        $enrollments = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.last_name')
            ->values();

        $studentIds = $enrollments
            ->pluck('student.id')
            ->filter()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Grading Item Scores
        |--------------------------------------------------------------------------
        |
        | Only scores belonging to this section and to grading items belonging
        | to this section are loaded.
        |
        */

        $itemIdValues = $items
            ->pluck('id')
            ->values();

        if ($itemIdValues->isEmpty()) {
            $itemScores = collect();
        } else {
            $itemScores = GradingItemScore::query()
                ->where('section_id', $section->id)
                ->whereIn(
                    'grading_item_id',
                    $itemIdValues
                )
                ->get()
                ->groupBy(
                    fn ($score) =>
                        "{$score->student_id}_{$score->grading_item_id}"
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Assignment Grades
        |--------------------------------------------------------------------------
        */

        $assignmentIdValues = $assignments
            ->pluck('id')
            ->values();

        if (
            $assignmentIdValues->isEmpty() ||
            $studentIds->isEmpty()
        ) {
            $assignmentGrades = collect();
        } else {
            $assignmentGrades = Grade::query()
                ->where('section_id', $section->id)
                ->whereNotNull('assignment_id')
                ->whereIn(
                    'assignment_id',
                    $assignmentIdValues
                )
                ->whereIn(
                    'student_id',
                    $studentIds
                )
                ->get()
                ->groupBy(
                    fn ($grade) =>
                        "{$grade->student_id}_{$grade->assignment_id}"
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Transmutation Configuration
        |--------------------------------------------------------------------------
        */

        $hasCustomScale = TransmutationScale::query()
            ->where('section_id', $section->id)
            ->exists();

        /*
        |--------------------------------------------------------------------------
        | Group Grading Items By Component
        |--------------------------------------------------------------------------
        */

        $itemsByComponent = $items->groupBy(
            'component_id'
        );

        /*
        |--------------------------------------------------------------------------
        | Standalone Assignments
        |--------------------------------------------------------------------------
        |
        | Assignments without a grading component are displayed separately.
        |
        */

        $standaloneAssignments = $assignments
            ->whereNull('component_id')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Period Components
        |--------------------------------------------------------------------------
        */

        $midtermComponents = $components->where(
            'period',
            'midterm'
        );

        $finalsComponents = $components->where(
            'period',
            'finals'
        );

        /*
        |--------------------------------------------------------------------------
        | Student Rows
        |--------------------------------------------------------------------------
        */

        $rows = $enrollments
            ->map(function ($enrollment) use (
                $components,
                $items,
                $itemScores,
                $standaloneAssignments,
                $assignmentGrades,
                $section,
                $itemsByComponent,
                $midtermComponents,
                $finalsComponents,
                $assignmentIds,
                $itemIds
            ) {
                $student = $enrollment->student;

                /*
                |--------------------------------------------------------------------------
                | Build Student Scores
                |--------------------------------------------------------------------------
                */

                $scores = $this->buildStudentScores(
                    studentId: $student->id,
                    items: $items,
                    itemScores: $itemScores,
                    assignmentGrades: $assignmentGrades
                );

                /*
                |--------------------------------------------------------------------------
                | Midterm Grade
                |--------------------------------------------------------------------------
                */

                $midtermGrade = $this->computePeriodGrade(
                    $midtermComponents,
                    $scores,
                    $itemsByComponent
                );

                /*
                |--------------------------------------------------------------------------
                | Finals Grade
                |--------------------------------------------------------------------------
                */

                $finalsGrade = $this->computePeriodGrade(
                    $finalsComponents,
                    $scores,
                    $itemsByComponent
                );

                /*
                |--------------------------------------------------------------------------
                | Overall Grade
                |--------------------------------------------------------------------------
                */

                $totalGrade = $this->computeTotalGrade(
                    $midtermGrade,
                    $finalsGrade,
                    $components,
                    $scores,
                    $itemsByComponent
                );

                /*
                |--------------------------------------------------------------------------
                | Final Transmuted Grade
                |--------------------------------------------------------------------------
                */

                $finalGrade = $totalGrade !== null
                    ? TransmutationScale::transmute(
                        $totalGrade,
                        $section->id
                    )
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Standalone Assignment Grades
                |--------------------------------------------------------------------------
                */

                $studentAssignmentGrades =
                    $this->transformAssignmentGrades(
                        studentId: $student->id,
                        assignments: $standaloneAssignments,
                        assignmentGrades: $assignmentGrades,
                        assignmentIds: $assignmentIds
                    );

                /*
                |--------------------------------------------------------------------------
                | Student Row
                |--------------------------------------------------------------------------
                */

                return [
                    'student' => [
                        'id' => $this->encryptId(
                            $student->id
                        ),

                        'first_name' =>
                            $student->first_name,

                        'middle_name' =>
                            $student->middle_name,

                        'last_name' =>
                            $student->last_name,

                        'student_number' =>
                            $student->student_number,
                    ],

                    'enrollment_id' =>
                        $this->encryptId(
                            $enrollment->id
                        ),

                    'scores' => $this->transformScores(
                        $scores,
                        $items,
                        $itemIds
                    ),

                    'assignment_grades' =>
                        $studentAssignmentGrades,

                    'midterm_grade' =>
                        $midtermGrade,

                    'finals_grade' =>
                        $finalsGrade,

                    'total_grade' =>
                        $totalGrade,

                    'final_grade' =>
                        $finalGrade,

                    'midterm_final_grade' =>
                        $midtermGrade !== null
                            ? TransmutationScale::transmute(
                                $midtermGrade,
                                $section->id
                            )
                            : null,

                    'finals_final_grade' =>
                        $finalsGrade !== null
                            ? TransmutationScale::transmute(
                                $finalsGrade,
                                $section->id
                            )
                            : null,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Calculate Weights
        |--------------------------------------------------------------------------
        */

        $midtermWeight = $components
            ->where('period', 'midterm')
            ->sum('weight_percentage');

        $finalsWeight = $components
            ->where('period', 'finals')
            ->sum('weight_percentage');

        $generalWeight = $components
            ->whereNull('period')
            ->sum('weight_percentage');

        /*
        |--------------------------------------------------------------------------
        | Transform Components
        |--------------------------------------------------------------------------
        */

        $transformedComponents = $components
            ->map(
                fn ($component) => [
                    'id' =>
                        $componentIds[$component->id],

                    'name' =>
                        $component->name,

                    'weight_percentage' =>
                        $component->weight_percentage,

                    'max_score' =>
                        $component->max_score,

                    'order' =>
                        $component->order,

                    'period' =>
                        $component->period,

                    'is_locked' =>
                        (bool) $component->is_locked,
                ]
            )
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Transform Grading Items
        |--------------------------------------------------------------------------
        */

        $transformedItems = $items
            ->map(
                fn ($item) => [
                    'id' =>
                        $itemIds[$item->id],

                    'component_id' =>
                        $item->component_id !== null
                            ? (
                                $componentIds[
                                    $item->component_id
                                ] ?? null
                            )
                            : null,

                    'assignment_id' =>
                        $item->assignment_id !== null
                            ? (
                                $assignmentIds[
                                    $item->assignment_id
                                ] ?? null
                            )
                            : null,

                    'name' =>
                        $item->name,

                    'max_score' =>
                        $item->max_score,

                    'order' =>
                        $item->order,

                    'is_enabled' =>
                        (bool) $item->is_enabled,
                ]
            )
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Transform Standalone Assignments
        |--------------------------------------------------------------------------
        */

        $transformedAssignments = $standaloneAssignments
            ->map(
                fn ($assignment) => [
                    'id' =>
                        $assignmentIds[$assignment->id],

                    'title' =>
                        $assignment->title,

                    'max_score' =>
                        $assignment->max_score,

                    'type' =>
                        $assignment->type,

                    'period' =>
                        $assignment->period,

                    'category' =>
                        $assignment->category,

                    'component_id' =>
                        null,
                ]
            )
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Return Complete Class Record Data
        |--------------------------------------------------------------------------
        */

        return [
            'section' => [
                'id' =>
                    $this->encryptId(
                        $section->id
                    ),

                'name' =>
                    $section->name,

                'subject' => $section->subject
                    ? [
                        'id' =>
                            $this->encryptId(
                                $section->subject->id
                            ),

                        'code' =>
                            $section->subject->code,

                        'name' =>
                            $section->subject->name,
                    ]
                    : null,

                'semester' => $section->semester
                    ? [
                        'name' =>
                            $section->semester->name,

                        'school_year' =>
                            $section->semester->school_year,
                    ]
                    : null,

                'faculty' => $section->faculty
                    ? [
                        'name' =>
                            $section->faculty->name,
                    ]
                    : null,
            ],

            'components' =>
                $transformedComponents,

            'items' =>
                $transformedItems,

            'assignments' =>
                $transformedAssignments,

            'midtermWeight' =>
                $midtermWeight,

            'finalsWeight' =>
                $finalsWeight,

            'generalWeight' =>
                $generalWeight,

            'rows' =>
                $rows->all(),

            'hasCustomScale' =>
                $hasCustomScale,

            'defaultScale' =>
                TransmutationScale::defaultScale(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build Student Scores
    |--------------------------------------------------------------------------
    */

    private function buildStudentScores(
        int $studentId,
        $items,
        $itemScores,
        $assignmentGrades
    ): array {
        $scores = [];

        foreach ($items as $item) {
            $itemKey =
                "{$studentId}_{$item->id}";

            /*
            |--------------------------------------------------------------------------
            | Manual Grading Item Score
            |--------------------------------------------------------------------------
            */

            $manualScore = $itemScores
                ->get($itemKey)
                ?->first()
                ?->score;

            if ($manualScore !== null) {
                $scores[$item->id] =
                    $manualScore;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Assignment-Backed Grading Item
            |--------------------------------------------------------------------------
            */

            if ($item->assignment_id !== null) {
                $assignmentKey =
                    "{$studentId}_{$item->assignment_id}";

                $grade = $assignmentGrades
                    ->get($assignmentKey)
                    ?->first();

                $scores[$item->id] =
                    $grade?->raw_score;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | No Score
            |--------------------------------------------------------------------------
            */

            $scores[$item->id] = null;
        }

        return $scores;
    }

    /*
    |--------------------------------------------------------------------------
    | Compute Period Grade
    |--------------------------------------------------------------------------
    */

    private function computePeriodGrade(
        $periodComponents,
        array $scores,
        $itemsByComponent
    ): ?float {
        if ($periodComponents->isEmpty()) {
            return null;
        }

        $weightedSum = 0;
        $weightTotal = 0;

        foreach ($periodComponents as $component) {
            $componentItems =
                $itemsByComponent->get(
                    $component->id,
                    collect()
                );

            if ($componentItems->isEmpty()) {
                continue;
            }

            $rawScore = 0;
            $maxScore = 0;
            $hasScore = false;

            foreach ($componentItems as $item) {
                $maxScore +=
                    $item->max_score;

                $value =
                    $scores[$item->id] ?? null;

                if ($value !== null) {
                    $hasScore = true;
                    $rawScore += $value;
                }
            }

            if (
                !$hasScore ||
                $maxScore <= 0
            ) {
                continue;
            }

            $percentage =
                ($rawScore / $maxScore) * 100;

            $weightedSum +=
                ($percentage / 100) *
                $component->weight_percentage;

            $weightTotal +=
                $component->weight_percentage;
        }

        if ($weightTotal <= 0) {
            return null;
        }

        return round(
            ($weightedSum / $weightTotal) * 100,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Compute Total Grade
    |--------------------------------------------------------------------------
    */

    private function computeTotalGrade(
        ?float $midtermGrade,
        ?float $finalsGrade,
        $components,
        array $scores,
        $itemsByComponent
    ): ?float {
        /*
        |--------------------------------------------------------------------------
        | Midterm + Finals
        |--------------------------------------------------------------------------
        */

        if (
            $midtermGrade !== null &&
            $finalsGrade !== null
        ) {
            return round(
                (
                    $midtermGrade +
                    $finalsGrade
                ) / 2,
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Only One Period
        |--------------------------------------------------------------------------
        */

        if (
            $midtermGrade !== null ||
            $finalsGrade !== null
        ) {
            return $midtermGrade
                ?? $finalsGrade;
        }

        /*
        |--------------------------------------------------------------------------
        | General Weighted Grade
        |--------------------------------------------------------------------------
        */

        $weightedSum = 0;
        $weightTotal = 0;

        foreach ($components as $component) {
            $componentItems =
                $itemsByComponent->get(
                    $component->id,
                    collect()
                );

            if ($componentItems->isEmpty()) {
                continue;
            }

            $rawScore = 0;
            $maxScore = 0;
            $hasScore = false;

            foreach ($componentItems as $item) {
                $maxScore +=
                    $item->max_score;

                $value =
                    $scores[$item->id] ?? null;

                if ($value !== null) {
                    $hasScore = true;
                    $rawScore += $value;
                }
            }

            if (
                !$hasScore ||
                $maxScore <= 0
            ) {
                continue;
            }

            $percentage =
                ($rawScore / $maxScore) * 100;

            $weightedSum +=
                ($percentage / 100) *
                $component->weight_percentage;

            $weightTotal +=
                $component->weight_percentage;
        }

        if ($weightTotal <= 0) {
            return null;
        }

        return round(
            ($weightedSum / $weightTotal) * 100,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Standalone Assignment Grades
    |--------------------------------------------------------------------------
    */

    private function transformAssignmentGrades(
        int $studentId,
        $assignments,
        $assignmentGrades,
        $assignmentIds
    ): array {
        $result = [];

        foreach ($assignments as $assignment) {
            $assignmentKey =
                "{$studentId}_{$assignment->id}";

            $grade = $assignmentGrades
                ->get($assignmentKey)
                ?->first();

            $encryptedAssignmentId =
                $assignmentIds[
                    $assignment->id
                ];

            $result[$encryptedAssignmentId] =
                $grade
                    ? [
                        'score' =>
                            $grade->raw_score,

                        'max' =>
                            $grade->max_score,

                        'pct' =>
                            $grade->max_score > 0
                                ? round(
                                    (
                                        $grade->raw_score /
                                        $grade->max_score
                                    ) * 100,
                                    1
                                )
                                : 0,

                        'released' =>
                            (bool) $grade->is_released,
                    ]
                    : null;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Component Grade
    |--------------------------------------------------------------------------
    */

    public function updateGrade(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'student_id' =>
                'required|string',

            'section_id' =>
                'required|string',

            'component_id' =>
                'required|string',

            'score' =>
                'nullable|numeric|min:0',
        ]);

        $studentId = $this->decryptId(
            $validated['student_id']
        );

        $sectionId = $this->decryptId(
            $validated['section_id']
        );

        $componentId = $this->decryptId(
            $validated['component_id']
        );

        $section = Section::query()
            ->findOrFail($sectionId);

        $component = GradingComponent::query()
            ->where('section_id', $section->id)
            ->findOrFail($componentId);

        /*
        |--------------------------------------------------------------------------
        | Validate Maximum Score
        |--------------------------------------------------------------------------
        */

        if (
            $validated['score'] !== null &&
            $validated['score'] > $component->max_score
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    "Score cannot exceed {$component->max_score} for {$component->name}.",
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Grade
        |--------------------------------------------------------------------------
        */

        if ($validated['score'] === null) {
            Grade::query()
                ->where(
                    'student_id',
                    $studentId
                )
                ->where(
                    'section_id',
                    $sectionId
                )
                ->where(
                    'component_id',
                    $componentId
                )
                ->whereNull('submission_id')
                ->delete();

            return response()->json([
                'success' => true,

                'message' =>
                    'Grade removed successfully.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Save Grade
        |--------------------------------------------------------------------------
        */

        Grade::updateOrCreate(
            [
                'student_id' =>
                    $studentId,

                'section_id' =>
                    $sectionId,

                'component_id' =>
                    $componentId,

                'submission_id' =>
                    null,
            ],
            [
                'assignment_id' =>
                    null,

                'raw_score' =>
                    $validated['score'],

                'max_score' =>
                    $component->max_score,

                'is_released' =>
                    false,
            ]
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Grade saved successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Grading Item Score
    |--------------------------------------------------------------------------
    */

    public function updateItemScore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'string'],
            'section_id' => ['required', 'string'],
            'item_id' => ['required', 'string'],
            'score' => ['nullable', 'numeric', 'min:0'],
        ]);

        // ---------------------------------------------------------
        // Resolve encrypted IDs
        // ---------------------------------------------------------

        $section = $this->resolveSection($validated['section_id']);

        $studentId = $this->decryptId($validated['student_id']);
        $itemId = $this->decryptId($validated['item_id']);

        // ---------------------------------------------------------
        // Verify the grading item belongs to this section
        // ---------------------------------------------------------

        $item = GradingItem::query()
            ->where('id', $itemId)
            ->where('section_id', $section->id)
            ->firstOrFail();

        // ---------------------------------------------------------
        // Validate score against the item's maximum score
        // ---------------------------------------------------------

        if (
            $validated['score'] !== null &&
            (float) $validated['score'] > (float) $item->max_score
        ) {
            return response()->json([
                'success' => false,
                'message' => "Score cannot exceed {$item->max_score}.",
            ], 422);
        }

        // ---------------------------------------------------------
        // Save / update the item score
        // ---------------------------------------------------------

        $itemScore = GradingItemScore::updateOrCreate(
            [
                'grading_item_id' => $item->id,
                'student_id' => $studentId,
                'section_id' => $section->id,
            ],
            [
                'score' => $validated['score'],
            ]
        );

        // ---------------------------------------------------------
        // Reload the class record so the frontend gets updated data
        // ---------------------------------------------------------

        $data = $this->getClassRecordData($validated['section_id']);

        $updatedRow = collect($data['rows'])
            ->first(function ($row) use ($studentId) {
                try {
                    return $this->decryptId($row['student']['id']) === $studentId;
                } catch (\Throwable $e) {
                    return false;
                }
            });

        return response()->json([
            'success' => true,
            'message' => 'Score saved successfully.',
            'data' => [
                'score' => [
                    'id' => $this->encryptId($itemScore->id),
                    'grading_item_id' => $this->encryptId($itemScore->grading_item_id),
                    'student_id' => $this->encryptId($itemScore->student_id),
                    'section_id' => $this->encryptId($itemScore->section_id),
                    'score' => $itemScore->score,
                    'is_released' => (bool) $itemScore->is_released,
                ],
                'row' => $updatedRow,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Release All Grades
    |--------------------------------------------------------------------------
    |
    | Releases:
    |
    | 1. Assignment grades
    | 2. Manual grading item scores
    |
    | Both are restricted to the selected section.
    |
    */

    public function releaseAll(
        string $sectionId
    ): JsonResponse {
        $section = $this->resolveSection(
            $sectionId
        );

        /*
        |--------------------------------------------------------------------------
        | Release Assignment Grades
        |--------------------------------------------------------------------------
        |
        | Grade records are already associated with the section and assignment.
        |
        */

        $assignmentGradesReleased = Grade::query()
            ->where('section_id', $section->id)
            ->whereNotNull('assignment_id')
            ->whereHas(
                'submission.assignment',
                function ($query) use ($section) {
                    $query->where(
                        'section_id',
                        $section->id
                    );
                }
            )
            ->update([
                'is_released' => true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Release Manual Grading Item Scores
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | We verify BOTH:
        |
        | - grading_item_scores.section_id
        | - grading_items.section_id
        |
        | This prevents a score belonging to another section from being
        | accidentally released.
        |
        */

        $itemScoresReleased = GradingItemScore::query()
            ->where(
                'section_id',
                $section->id
            )
            ->whereHas(
                'item',
                function ($query) use ($section) {
                    $query->where(
                        'section_id',
                        $section->id
                    );
                }
            )
            ->update([
                'is_released' => true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Return Result
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
                'All grades for this section have been released.',

            'data' => [
                'section_id' =>
                    $this->encryptId(
                        $section->id
                    ),

                'assignment_grades_released' =>
                    $assignmentGradesReleased,

                'item_scores_released' =>
                    $itemScoresReleased,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Section
    |--------------------------------------------------------------------------
    */

    private function resolveSection(
        string $encryptedId
    ): Section {
        $id = $this->decryptId(
            $encryptedId
        );

        return Section::query()
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
    | Transform Scores
    |--------------------------------------------------------------------------
    */

    private function transformScores(
        array $scores,
        $items,
        $itemIds
    ): array {
        $result = [];

        foreach ($items as $item) {
            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | Do not encrypt the item ID again here.
            |
            | The same encrypted ID must be used for:
            |
            | items[].id
            | rows[].scores keys
            |
            */

            $encryptedItemId =
                $itemIds[$item->id];

            $result[$encryptedItemId] =
                $scores[$item->id] ?? null;
        }

        return $result;
    }
}