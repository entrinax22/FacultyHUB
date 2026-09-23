<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('sections/Index');
    }

    public function create(): Response
    {
        $semesters = Semester::query()
            ->orderByDesc('is_active')
            ->orderByDesc('school_year')
            ->orderBy('name')
            ->get()
            ->map(fn ($semester) => [
                'id' => Crypt::encryptString((string) $semester->id),
                'name' => $semester->name,
                'school_year' => $semester->school_year,
                'is_active' => (bool) $semester->is_active,
            ])
            ->values();

        $subjects = Subject::query()
            ->orderBy('code')
            ->get()
            ->map(fn ($subject) => [
                'id' => Crypt::encryptString((string) $subject->id),
                'code' => $subject->code,
                'name' => $subject->name,
            ])
            ->values();

        return Inertia::render('sections/Form', [
            'semesters' => $semesters,
            'subjects' => $subjects,
        ]);
    }

    public function show(string $id): Response
    {
        try {
            $sectionId = Crypt::decryptString($id);

            $section = Section::with([
                'semester',
                'subject',
                'faculty',
            ])->findOrFail($sectionId);

            return Inertia::render('sections/Show', [
                'sectionId' => $id,
            ]);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    public function edit(string $id): Response
    {
        try {
            $sectionId = Crypt::decryptString($id);

            $section = Section::findOrFail($sectionId);

            /*
            |--------------------------------------------------------------------------
            | SEMESTERS
            |--------------------------------------------------------------------------
            */

            $semesterRecords = Semester::query()
                ->orderByDesc('is_active')
                ->orderByDesc('school_year')
                ->orderBy('name')
                ->get();

            $semesters = $semesterRecords->map(fn ($semester) => [
                'id' => Crypt::encryptString((string) $semester->id),
                'name' => $semester->name,
                'school_year' => $semester->school_year,
                'is_active' => (bool) $semester->is_active,
            ])->values();

            /*
            |--------------------------------------------------------------------------
            | SUBJECTS
            |--------------------------------------------------------------------------
            */

            $subjectRecords = Subject::query()
                ->orderBy('code')
                ->get();

            $subjects = $subjectRecords->map(fn ($subject) => [
                'id' => Crypt::encryptString((string) $subject->id),
                'code' => $subject->code,
                'name' => $subject->name,
            ])->values();

            /*
            |--------------------------------------------------------------------------
            | FIND SELECTED OPTION
            |--------------------------------------------------------------------------
            */

            $semesterIndex = $semesterRecords->search(
                fn ($semester) =>
                    (int) $semester->id === (int) $section->semester_id
            );

            $subjectIndex = $subjectRecords->search(
                fn ($subject) =>
                    (int) $subject->id === (int) $section->subject_id
            );

            return Inertia::render('sections/Form', [
                'section' => [
                    'id' => Crypt::encryptString(
                        (string) $section->id
                    ),

                    'name' => $section->name,

                    'semester_id' => $semesterIndex !== false
                        ? $semesters[$semesterIndex]['id']
                        : '',

                    'subject_id' => $subjectIndex !== false
                        ? $subjects[$subjectIndex]['id']
                        : '',

                    'schedule' => $section->schedule,
                    'room' => $section->room,
                ],

                'semesters' => $semesters,
                'subjects' => $subjects,
            ]);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API - Sections
    |--------------------------------------------------------------------------
    */

    public function sections_data(Request $request): JsonResponse
    {
        try {
            $query = Section::query()
                ->with([
                    'semester',
                    'subject',
                    'faculty',
                ])
                ->withCount('enrollments');

            /*
            |--------------------------------------------------------------------------
            | Faculty Restriction
            |--------------------------------------------------------------------------
            */

            if ($request->user()->isFaculty()) {
                $query->where(
                    'faculty_id',
                    $request->user()->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            */

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where(
                        'sections.name',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhereHas(
                            'subject',
                            function ($subject) use ($search) {
                                $subject
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )
                        ->orWhereHas(
                            'faculty',
                            function ($faculty) use ($search) {
                                $faculty->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Semester Filter
            |--------------------------------------------------------------------------
            */

            $semesterId = null;

            if ($request->filled('semester_id')) {
                try {
                    $semesterId = Crypt::decryptString(
                        $request->get('semester_id')
                    );

                    $query->where(
                        'semester_id',
                        $semesterId
                    );
                } catch (DecryptException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid semester ID.',
                        'data' => [],
                        'pagination' => null,
                    ], 422);
                }
            } else {
                $activeSemester = Semester::getActive();

                if ($activeSemester) {
                    $semesterId = $activeSemester->id;

                    $query->where(
                        'semester_id',
                        $activeSemester->id
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */

            $perPage = min(
                max(
                    $request->integer(
                        'per_page',
                        20
                    ),
                    1
                ),
                100
            );

            $page = max(
                $request->integer(
                    'page',
                    1
                ),
                1
            );

            $sections = $query
                ->latest()
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            /*
            |--------------------------------------------------------------------------
            | Transform Sections
            |--------------------------------------------------------------------------
            */

            $sections->through(
                fn ($section) => [
                    'id' => Crypt::encryptString(
                        (string) $section->id
                    ),

                    'name' => $section->name,

                    'schedule' => $section->schedule,

                    'room' => $section->room,

                    'enrollments_count' =>
                        $section->enrollments_count,

                    'semester' => $section->semester
                        ? [
                            'id' => Crypt::encryptString(
                                (string) $section->semester->id
                            ),
                            'name' =>
                                $section->semester->name,
                            'school_year' =>
                                $section->semester->school_year,
                            'is_active' =>
                                (bool) $section->semester->is_active,
                        ]
                        : null,

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

                    'faculty' => $section->faculty
                        ? [
                            'id' => Crypt::encryptString(
                                (string) $section->faculty->id
                            ),
                            'name' =>
                                $section->faculty->name,
                            'email' =>
                                $section->faculty->email,
                        ]
                        : null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Semesters Filter
            |--------------------------------------------------------------------------
            */

            $semesters = Semester::query()
                ->orderByDesc('is_active')
                ->latest()
                ->get([
                    'id',
                    'name',
                    'school_year',
                    'is_active',
                ])
                ->map(
                    fn ($semester) => [
                        'id' => Crypt::encryptString(
                            (string) $semester->id
                        ),
                        'name' => $semester->name,
                        'school_year' =>
                            $semester->school_year,
                        'is_active' =>
                            (bool) $semester->is_active,
                    ]
                )
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Sections retrieved successfully.',

                'data' => $sections->items(),

                'pagination' => [
                    'current_page' =>
                        $sections->currentPage(),

                    'last_page' =>
                        $sections->lastPage(),

                    'per_page' =>
                        $sections->perPage(),

                    'total' =>
                        $sections->total(),

                    'from' =>
                        $sections->firstItem(),

                    'to' =>
                        $sections->lastItem(),

                    'has_more_pages' =>
                        $sections->hasMorePages(),

                    'next_page_url' =>
                        $sections->nextPageUrl(),

                    'previous_page_url' =>
                        $sections->previousPageUrl(),

                    'first_page_url' =>
                        $sections->url(1),

                    'last_page_url' =>
                        $sections->url(
                            $sections->lastPage()
                        ),
                ],

                'filters' => [
                    'search' =>
                        $request->get('search'),

                    'semester_id' =>
                        $semesterId
                            ? Crypt::encryptString(
                                (string) $semesterId
                            )
                            : null,

                    'semesters' =>
                        $semesters,
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sections.',
                'data' => [],
                'pagination' => null,
                'filters' => [
                    'semesters' => [],
                ],
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API - Section Details
    |--------------------------------------------------------------------------
    */

    public function section_data(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            /*
            |--------------------------------------------------------------------------
            | Resolve Section
            |--------------------------------------------------------------------------
            */

            $sectionId = Crypt::decryptString($id);

            $section = Section::with([
                'semester',
                'subject',
                'faculty',
            ])
                ->withCount('enrollments')
                ->findOrFail($sectionId);

            /*
            |--------------------------------------------------------------------------
            | Authorization
            |--------------------------------------------------------------------------
            |
            | Admin:
            |   Can access any section.
            |
            | Faculty:
            |   Can only access their own sections.
            |
            */

            $this->authorizeSection(
                $section,
                $request
            );

            /*
            |--------------------------------------------------------------------------
            | Enrollment Query
            |--------------------------------------------------------------------------
            */

            $query = $section
                ->enrollments()
                ->with('student');

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            */

            if ($search = $request->get('search')) {
                $query->whereHas(
                    'student',
                    function ($student) use ($search) {
                        $student->where(
                            function ($q) use ($search) {
                                $q->where(
                                    'student_no',
                                    'like',
                                    "%{$search}%"
                                )
                                    ->orWhere(
                                        'first_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'last_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'course',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */

            $perPage = min(
                max(
                    $request->integer(
                        'per_page',
                        20
                    ),
                    1
                ),
                100
            );

            $page = max(
                $request->integer(
                    'page',
                    1
                ),
                1
            );

            $enrollments = $query
                ->latest()
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            /*
            |--------------------------------------------------------------------------
            | Transform Enrollments
            |--------------------------------------------------------------------------
            */

            $enrollments->through(
                fn ($enrollment) => [
                    'id' => Crypt::encryptString(
                        (string) $enrollment->id
                    ),

                    'student' => $enrollment->student
                        ? [
                            'id' => Crypt::encryptString(
                                (string) $enrollment->student->id
                            ),

                            'student_no' =>
                                $enrollment->student->student_no,

                            'name' => trim(
                                $enrollment->student->first_name .
                                ' ' .
                                $enrollment->student->last_name
                            ),

                            'email' =>
                                $enrollment->student->email,

                            'course' =>
                                $enrollment->student->course,

                            'year_level' =>
                                $enrollment->student->year_level,
                        ]
                        : null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' =>
                    'Section retrieved successfully.',

                'data' => [
                    'id' => Crypt::encryptString(
                        (string) $section->id
                    ),

                    'name' => $section->name,

                    'schedule' => $section->schedule,

                    'room' => $section->room,

                    'enrollments_count' =>
                        $section->enrollments_count,

                    'semester' => $section->semester
                        ? [
                            'id' => Crypt::encryptString(
                                (string) $section->semester->id
                            ),

                            'name' =>
                                $section->semester->name,

                            'school_year' =>
                                $section->semester->school_year,

                            'is_active' =>
                                (bool) $section->semester->is_active,
                        ]
                        : null,

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

                    'faculty' => $section->faculty
                        ? [
                            'id' => Crypt::encryptString(
                                (string) $section->faculty->id
                            ),

                            'name' =>
                                $section->faculty->name,

                            'email' =>
                                $section->faculty->email,
                        ]
                        : null,

                    /*
                    |--------------------------------------------------------------------------
                    | Paginated Enrollments
                    |--------------------------------------------------------------------------
                    */

                    'enrollments' =>
                        $enrollments->items(),
                ],

                /*
                |--------------------------------------------------------------------------
                | Pagination
                |--------------------------------------------------------------------------
                */

                'pagination' => [
                    'current_page' =>
                        $enrollments->currentPage(),

                    'last_page' =>
                        $enrollments->lastPage(),

                    'per_page' =>
                        $enrollments->perPage(),

                    'total' =>
                        $enrollments->total(),

                    'from' =>
                        $enrollments->firstItem(),

                    'to' =>
                        $enrollments->lastItem(),

                    'has_more_pages' =>
                        $enrollments->hasMorePages(),

                    'next_page_url' =>
                        $enrollments->nextPageUrl(),

                    'previous_page_url' =>
                        $enrollments->previousPageUrl(),

                    'first_page_url' =>
                        $enrollments->url(1),

                    'last_page_url' =>
                        $enrollments->url(
                            $enrollments->lastPage()
                        ),
                ],

                /*
                |--------------------------------------------------------------------------
                | Current Filters
                |--------------------------------------------------------------------------
                */

                'filters' => [
                    'search' =>
                        $request->get('search'),
                ],
            ], 200);

        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid section ID.',
            ], 404);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve section.',
            ], 500);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | API - Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $sectionId = Crypt::decryptString($id);

            $section = Section::findOrFail(
                $sectionId
            );

            $this->authorizeSection(
                $section,
                $request
            );

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'semester_id' => [
                    'required',
                ],

                'subject_id' => [
                    'required',
                ],

                'schedule' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'room' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
            ]);

            try {
                $semesterId = Crypt::decryptString(
                    $validated['semester_id']
                );

                $subjectId = Crypt::decryptString(
                    $validated['subject_id']
                );
            } catch (DecryptException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid semester or subject ID.',
                ], 422);
            }

            if (!Semester::where('id', $semesterId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected semester does not exist.',
                ], 422);
            }

            if (!Subject::where('id', $subjectId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected subject does not exist.',
                ], 422);
            }

            $section->update([
                'name' => $validated['name'],
                'semester_id' => $semesterId,
                'subject_id' => $subjectId,
                'schedule' =>
                    $validated['schedule'] ?? null,
                'room' =>
                    $validated['room'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully.',
                'data' => [
                    'id' => Crypt::encryptString(
                        (string) $section->id
                    ),
                    'name' => $section->name,
                    'schedule' => $section->schedule,
                    'room' => $section->room,
                    'semester_id' =>
                        Crypt::encryptString(
                            (string) $section->semester_id
                        ),
                    'subject_id' =>
                        Crypt::encryptString(
                            (string) $section->subject_id
                        ),
                ],
            ], 200);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid section ID.',
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update section.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API - Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $sectionId = Crypt::decryptString($id);

            $section = Section::findOrFail(
                $sectionId
            );

            $this->authorizeSection(
                $section,
                $request
            );

            $section->delete();

            return response()->json([
                'success' => true,
                'message' => 'Section deleted successfully.',
            ], 200);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid section ID.',
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete section.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API - Form Data
    |--------------------------------------------------------------------------
    */

    public function form_data(): JsonResponse
    {
        try {
            $semesters = Semester::query()
                ->orderByDesc('is_active')
                ->latest()
                ->get([
                    'id',
                    'name',
                    'school_year',
                    'is_active',
                ])
                ->map(
                    fn ($semester) => [
                        'id' => Crypt::encryptString(
                            (string) $semester->id
                        ),
                        'name' => $semester->name,
                        'school_year' =>
                            $semester->school_year,
                        'is_active' =>
                            (bool) $semester->is_active,
                    ]
                )
                ->values();

            $subjects = Subject::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                ])
                ->map(
                    fn ($subject) => [
                        'id' => Crypt::encryptString(
                            (string) $subject->id
                        ),
                        'code' => $subject->code,
                        'name' => $subject->name,
                    ]
                )
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Section form data retrieved successfully.',
                'data' => [
                    'semesters' => $semesters,
                    'subjects' => $subjects,
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve section form data.',
                'data' => [
                    'semesters' => [],
                    'subjects' => [],
                ],
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    private function authorizeSection(
        Section $section,
        Request $request
    ): void {
        if (
            $request->user()->isFaculty() &&
            $section->faculty_id !==
                $request->user()->id
        ) {
            abort(
                response()->json([
                    'success' => false,
                    'message' =>
                        'You do not have permission to access this section.',
                ], 403)
            );
        }
    }
}

