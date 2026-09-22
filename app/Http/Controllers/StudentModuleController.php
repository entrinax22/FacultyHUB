<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesStudent;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\GradingComponent;
use App\Models\GradingItemScore;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\Section;
use App\Models\Semester;
use App\Models\TransmutationScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentModuleController extends Controller
{
    use ResolvesStudent;

    /*
    |--------------------------------------------------------------------------
    | Student Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request): Response
    {
        $student = $this->resolveStudent($request);

        $enrollments = $student->enrollments()
            ->with([
                'section.subject',
                'section.semester',
                'section.faculty',
                'semester',
            ])
            ->where('status', 'active')
            ->get();

        $sectionIds = $enrollments->pluck('section_id');

        /*
        |--------------------------------------------------------------------------
        | Upcoming Assignments
        |--------------------------------------------------------------------------
        */

        $submittedIds = $student->submissions()
            ->pluck('assignment_id');

        $upcoming = Assignment::whereIn('section_id', $sectionIds)
            ->where('is_published', true)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(14))
            ->whereNotIn('id', $submittedIds)
            ->with([
                'section.subject',
            ])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($assignment) => [
                'id' => $this->encryptId($assignment->id),

                'title' => $assignment->title,

                'due_date' => $assignment->due_date?->toIso8601String(),

                'section_name' => $assignment->section?->name,

                'subject_code' => $assignment
                    ->section
                    ?->subject
                    ?->code,

                'section_id' => $this->encryptId(
                    $assignment->section_id
                ),
            ])
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Recently Released Grades
        |--------------------------------------------------------------------------
        */

        $recentGrades = $student->grades()
            ->where('is_released', true)
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereNotNull('assignment_id')
            ->with([
                'assignment.section.subject',
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($grade) => [
                'id' => $this->encryptId($grade->id),

                'raw_score' => $grade->raw_score,

                'max_score' => $grade->max_score,

                'assignment_title' => $grade
                    ->assignment
                    ?->title,

                'subject_code' => $grade
                    ->assignment
                    ?->section
                    ?->subject
                    ?->code,

                'assignment_id' => $this->encryptId(
                    $grade->assignment_id
                ),

                'section_id' => $grade->assignment?->section_id
                    ? $this->encryptId(
                        $grade->assignment->section_id
                    )
                    : null,
            ])
            ->values();

        return Inertia::render('student/Dashboard', [
            'student' => $this->transformStudent($student),

            'enrollments' => $enrollments
                ->map(fn ($enrollment) =>
                    $this->transformEnrollment($enrollment)
                )
                ->values(),

            'upcoming' => $upcoming,

            'recentGrades' => $recentGrades,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Section Modules
    |--------------------------------------------------------------------------
    */

    public function sectionModules(
        Request $request,
        string $id
    ): Response {
        $student = $this->resolveStudent($request);

        $section = $this->resolveSection($id);

        $this->ensureStudentEnrolled(
            $student->id,
            $section->id
        );

        $section->load([
            'subject',
            'semester',
            'faculty',
        ]);

        $modules = $section->modules()
            ->where('is_published', true)
            ->with('files')
            ->get()
            ->map(function (Module $module) use ($student) {

                $module->is_read = $module->isReadByStudent(
                    $student->id
                );

                return $this->transformModule($module);
            })
            ->values();

        $readCount = $modules
            ->filter(fn ($module) => $module['is_read'])
            ->count();

        return Inertia::render('student/SectionModules', [
            'section' => $this->transformSection($section),

            'modules' => $modules,

            'progress' => [
                'read' => $readCount,
                'total' => $modules->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | View Module
    |--------------------------------------------------------------------------
    */

    public function viewModule(
        Request $request,
        string $sectionId,
        string $moduleId
    ): Response {
        $student = $this->resolveStudent($request);

        $section = $this->resolveSection($sectionId);

        $module = $this->resolveModule($moduleId);

        /*
        |--------------------------------------------------------------------------
        | Verify Module Belongs to Section
        |--------------------------------------------------------------------------
        */

        if ((int) $module->section_id !== (int) $section->id) {
            abort(404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Module is Published
        |--------------------------------------------------------------------------
        */

        if (! $module->is_published) {
            abort(404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Student Enrollment
        |--------------------------------------------------------------------------
        */

        $this->ensureStudentEnrolled(
            $student->id,
            $section->id
        );

        $module->load([
            'files',
            'section.subject',
            'section.semester',
        ]);

        $isRead = $module->isReadByStudent(
            $student->id
        );

        return Inertia::render('student/ModuleView', [
            'module' => $this->transformModule($module),

            'isRead' => $isRead,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Student Grades
    |--------------------------------------------------------------------------
    */

    public function grades(
        Request $request,
        string $id
    ): Response {
        $student = $this->resolveStudent($request);

        $section = $this->resolveSection($id);

        $this->ensureStudentEnrolled(
            $student->id,
            $section->id
        );

        $section->load([
            'subject',
            'semester',
        ]);

        $components = GradingComponent::where(
            'section_id',
            $section->id
        )
            ->with([
                'items' => fn ($query) => $query
                    ->where('is_enabled', true)
                    ->orderBy('order'),
            ])
            ->orderBy('order')
            ->get()
            ->map(function ($component) use ($student) {

                $items = $component->items
                    ->map(function ($item) use ($student) {

                        $scoreRecord = GradingItemScore::where(
                            'grading_item_id',
                            $item->id
                        )
                            ->where(
                                'student_id',
                                $student->id
                            )
                            ->where(
                                'is_released',
                                true
                            )
                            ->first();

                        return [
                            'id' => $this->encryptId(
                                $item->id
                            ),

                            'name' => $item->name,

                            'max_score' => $item->max_score,

                            'score' => $scoreRecord?->score,

                            'is_released' =>
                                $scoreRecord !== null,

                            'score_id' => $scoreRecord
                                ? $this->encryptId(
                                    $scoreRecord->id
                                )
                                : null,
                        ];
                    })
                    ->values();

                $releasedItems = $items->filter(
                    fn ($item) =>
                        $item['is_released']
                );

                $earned = $releasedItems->sum('score');

                $total = $releasedItems->sum('max_score');

                $percentage = $total > 0
                    ? round(
                        ($earned / $total) * 100,
                        2
                    )
                    : null;

                return [
                    'id' => $this->encryptId(
                        $component->id
                    ),

                    'name' => $component->name,

                    'weight' =>
                        $component->weight_percentage,

                    'items' => $items,

                    'earned' =>
                        $releasedItems->count() > 0
                            ? $earned
                            : null,

                    'total' =>
                        $total > 0
                            ? $total
                            : null,

                    'percentage' => $percentage,

                    'weighted' => $percentage !== null
                        ? round(
                            $percentage *
                            (
                                $component->weight_percentage /
                                100
                            ),
                            2
                        )
                        : null,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Weighted Grade
        |--------------------------------------------------------------------------
        */

        $weightedTotal = $components
            ->filter(
                fn ($component) =>
                    $component['weighted'] !== null
            )
            ->sum('weighted');

        $allHaveWeighted = $components->every(
            fn ($component) =>
                $component['weighted'] !== null
        );

        $transmutedGrade = $allHaveWeighted &&
            $components->isNotEmpty()
                ? TransmutationScale::transmute(
                    $weightedTotal,
                    $section->id
                )
                : null;

        return Inertia::render('student/Grades', [
            'section' =>
                $this->transformSection($section),

            'components' => $components,

            'weightedTotal' =>
                $allHaveWeighted &&
                $components->isNotEmpty()
                    ? round($weightedTotal, 2)
                    : null,

            'transmutedGrade' =>
                $transmutedGrade,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Browse Sections
    |--------------------------------------------------------------------------
    */

    public function browseSections(
        Request $request
    ): Response {
        $student = $this->resolveStudent($request);

        $activeSemester = Semester::getActive();

        $enrolledSectionIds = $student->enrollments()
            ->where('status', 'active')
            ->pluck('section_id');

        $query = Section::with([
            'subject',
            'semester',
            'faculty',
        ])
            ->withCount([
                'enrollments' => fn ($query) =>
                    $query->where(
                        'status',
                        'active'
                    ),
            ]);

        if ($activeSemester) {
            $query->where(
                'semester_id',
                $activeSemester->id
            );
        }

        $sections = $query
            ->latest()
            ->get()
            ->map(fn ($section) => [
                'id' => $this->encryptId(
                    $section->id
                ),

                'name' => $section->name,

                'schedule' => $section->schedule,

                'subject_code' => $section
                    ->subject
                    ?->code,

                'subject_name' => $section
                    ->subject
                    ?->name,

                'faculty_name' => $section
                    ->faculty
                    ?->name,

                'semester' => $section->semester
                    ? $section->semester->name .
                        ' ' .
                        $section->semester->school_year
                    : null,

                'enrollments_count' =>
                    $section->enrollments_count,

                'is_enrolled' =>
                    $enrolledSectionIds->contains(
                        $section->id
                    ),
            ])
            ->values();

        return Inertia::render('student/BrowseSections', [
            'sections' => $sections,

            'activeSemester' => $activeSemester
                ? [
                    'id' => $this->encryptId(
                        $activeSemester->id
                    ),

                    'name' => $activeSemester->name,

                    'school_year' =>
                        $activeSemester->school_year,
                ]
                : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Self Enroll
    |--------------------------------------------------------------------------
    */

    public function selfEnroll(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $student = $this->resolveStudent($request);

            $section = $this->resolveSection($id);

            $alreadyEnrolled = Enrollment::where(
                'student_id',
                $student->id
            )
                ->where(
                    'section_id',
                    $section->id
                )
                ->exists();

            if ($alreadyEnrolled) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'You are already enrolled in this section.',
                ], 422);
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,

                'section_id' => $section->id,

                'semester_id' =>
                    $section->semester_id,

                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                    'You have enrolled in ' .
                    $section->name .
                    '.',

                'data' => [
                    'enrollment_id' =>
                        $this->encryptId(
                            $enrollment->id
                        ),

                    'section_id' =>
                        $this->encryptId(
                            $section->id
                        ),

                    'status' =>
                        $enrollment->status,
                ],
            ], 201);

        } catch (ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,

                'message' =>
                    'Failed to enroll in this section.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Module as Read
    |--------------------------------------------------------------------------
    */

    public function markRead(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $student = $this->resolveStudent($request);

            $module = $this->resolveModule($id);

            if (! $module->is_published) {
                abort(404);
            }

            $enrolled = $student->enrollments()
                ->where(
                    'section_id',
                    $module->section_id
                )
                ->where(
                    'status',
                    'active'
                )
                ->exists();

            if (! $enrolled) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'You are not enrolled in this section.',
                ], 403);
            }

            $progress = ModuleProgress::firstOrCreate([
                'student_id' => $student->id,

                'module_id' => $module->id,
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                    'Module marked as read.',

                'data' => [
                    'progress_id' =>
                        $this->encryptId(
                            $progress->id
                        ),

                    'module_id' =>
                        $this->encryptId(
                            $module->id
                        ),

                    'is_read' => true,
                ],
            ], 200);

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
                    'Failed to mark the module as read.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Encrypted ID
    |--------------------------------------------------------------------------
    */

    private function decryptId(
        string $id
    ): int {
        try {
            return (int) Crypt::decryptString($id);
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
        $sectionId = $this->decryptId($id);

        return Section::findOrFail($sectionId);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Module
    |--------------------------------------------------------------------------
    */

    private function resolveModule(
        string $id
    ): Module {
        $moduleId = $this->decryptId($id);

        return Module::findOrFail($moduleId);
    }

    /*
    |--------------------------------------------------------------------------
    | Enrollment Check
    |--------------------------------------------------------------------------
    */

    private function ensureStudentEnrolled(
        int $studentId,
        int $sectionId
    ): void {
        $enrolled = Enrollment::where(
            'student_id',
            $studentId
        )
            ->where(
                'section_id',
                $sectionId
            )
            ->where(
                'status',
                'active'
            )
            ->exists();

        if (! $enrolled) {
            abort(
                403,
                'You are not enrolled in this section.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Student
    |--------------------------------------------------------------------------
    */

    private function transformStudent($student): array
    {
        return [
            'id' => $this->encryptId(
                $student->id
            ),

            'student_number' => $student->student_number,

            'first_name' => $student->first_name,

            'middle_name' => $student->middle_name,

            'last_name' => $student->last_name,

            'email' => $student->email,

            'course' => $student->course,

            'year_level' => $student->year_level,

            'section' => $student->section,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Enrollment
    |--------------------------------------------------------------------------
    */

    private function transformEnrollment(
        Enrollment $enrollment
    ): array {
        return [
            'id' => $this->encryptId(
                $enrollment->id
            ),

            'student_id' => $this->encryptId(
                $enrollment->student_id
            ),

            'section_id' => $this->encryptId(
                $enrollment->section_id
            ),

            'semester_id' => $this->encryptId(
                $enrollment->semester_id
            ),

            'status' => $enrollment->status,

            'section' => $enrollment->section
                ? $this->transformSection(
                    $enrollment->section
                )
                : null,

            'semester' => $enrollment->semester
                ? $this->transformSemester(
                    $enrollment->semester
                )
                : null,
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
            'id' => $this->encryptId(
                $section->id
            ),

            'name' => $section->name,

            'schedule' => $section->schedule,

            'subject' => $section->subject
                ? [
                    'id' => $this->encryptId(
                        $section->subject->id
                    ),

                    'code' =>
                        $section->subject->code,

                    'name' =>
                        $section->subject->name,
                ]
                : null,

            'semester' => $section->semester
                ? $this->transformSemester(
                    $section->semester
                )
                : null,

            'faculty' => $section->faculty
                ? [
                    'id' => $this->encryptId(
                        $section->faculty->id
                    ),

                    'name' =>
                        $section->faculty->name,
                ]
                : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Semester
    |--------------------------------------------------------------------------
    */

    private function transformSemester(
        Semester $semester
    ): array {
        return [
            'id' => $this->encryptId(
                $semester->id
            ),

            'name' => $semester->name,

            'school_year' =>
                $semester->school_year,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Module
    |--------------------------------------------------------------------------
    */


    private function transformModule(
        Module $module
    ): array {
        return [
            'id' => $this->encryptId(
                $module->id
            ),

            'section_id' => $this->encryptId(
                $module->section_id
            ),

            'title' => $module->title,

            'description' => $module->description,

            'week_number' => $module->week_number,

            'is_published' =>
                (bool) $module->is_published,

            'is_read' =>
                (bool) ($module->is_read ?? false),

            'section' => $module->section
                ? [
                    'id' => $this->encryptId(
                        $module->section->id
                    ),

                    'name' => $module->section->name,

                    'subject' => $module->section->subject
                        ? [
                            'code' =>
                                $module->section->subject->code,

                            'name' =>
                                $module->section->subject->name,
                        ]
                        : null,

                    'semester' => $module->section->semester
                        ? [
                            'name' =>
                                $module->section->semester->name,

                            'school_year' =>
                                $module->section->semester->school_year,
                        ]
                        : null,
                ]
                : null,

            'files' => $module->files
                ->map(fn ($file) => [
                    'id' => $this->encryptId(
                        $file->id
                    ),

                    'file_name' => $file->name,

                    'file_type' => $file->mime_type,

                    'size_formatted' => $this->formatFileSize(
                        $file->size
                    ),

                    'url' => '/module-files/' .
                        $this->encryptId($file->id) .
                        '/serve',
                ])
                ->values()
                ->all(),
        ];
    }

    private function formatFileSize(
        int|float|null $bytes
    ): string {
        if ($bytes === null || $bytes <= 0) {
            return '0 Bytes';
        }

        $units = [
            'Bytes',
            'KB',
            'MB',
            'GB',
        ];

        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /*
    |--------------------------------------------------------------------------
    | Encrypt ID
    |--------------------------------------------------------------------------
    */

    private function encryptId(
        int|string|null $id
    ): ?string {
        if ($id === null) {
            return null;
        }

        return Crypt::encryptString(
            (string) $id
        );
    }
}
