<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('students/Index');
    }

    public function create(): Response
    {
        return Inertia::render('students/Form');
    }

    public function show(string $id): Response
    {
        $student = $this->resolveStudent($id);

        $student->load([
            'enrollments.section.subject',
            'enrollments.semester',
        ]);

        $student->id = Crypt::encryptString((string) $student->id);

        foreach ($student->enrollments as $enrollment) {
            $enrollment->id = Crypt::encryptString((string) $enrollment->id);

            if ($enrollment->section) {
                $enrollment->section->id = Crypt::encryptString(
                    (string) $enrollment->section->id
                );

                if ($enrollment->section->subject) {
                    $enrollment->section->subject->id = Crypt::encryptString(
                        (string) $enrollment->section->subject->id
                    );
                }
            }

            if ($enrollment->semester) {
                $enrollment->semester->id = Crypt::encryptString(
                    (string) $enrollment->semester->id
                );
            }
        }

        return Inertia::render('students/Show', [
            'student' => $student,
        ]);
    }

    public function edit(string $id): Response
    {
        $student = $this->resolveStudent($id);

        return Inertia::render('students/Form', [
            'student' => [
                'id' => Crypt::encryptString((string) $student->id),
                'student_no' => $student->student_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Student Data
    |--------------------------------------------------------------------------
    */

    public function students_data(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        $page = max($request->integer('page', 1), 1);

        $query = Student::query()
            ->withCount('enrollments');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('student_no', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('course', 'like', "%{$search}%");
            });
        }

        $students = $query
            ->latest()
            ->paginate(
                perPage: $perPage,
                page: $page
            )
            ->withQueryString();

        $students->through(function ($student) {
            return [
                'id' => Crypt::encryptString((string) $student->id),
                'student_no' => $student->student_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
                'enrollments_count' => $student->enrollments_count,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully.',
            'data' => $students->items(),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'from' => $students->firstItem(),
                'to' => $students->lastItem(),
                'has_more_pages' => $students->hasMorePages(),
                'next_page_url' => $students->nextPageUrl(),
                'previous_page_url' => $students->previousPageUrl(),
                'first_page_url' => $students->url(1),
                'last_page_url' => $students->url($students->lastPage()),
            ],
            'filters' => [
                'search' => $search,
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_no' => 'required|string|max:30|unique:students,student_no',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:students,email',
            'course' => 'required|string|max:100',
            'year_level' => 'required|integer|min:1|max:6',
        ]);

        $student = Student::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student added successfully.',
            'data' => [
                'id' => Crypt::encryptString((string) $student->id),
                'student_no' => $student->student_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, string $id): JsonResponse
    {
        $student = $this->resolveStudent($id);

        $validated = $request->validate([
            'student_no' => 'required|string|max:30|unique:students,student_no,' . $student->id,
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'course' => 'required|string|max:100',
            'year_level' => 'required|integer|min:1|max:6',
        ]);

        $student->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student updated successfully.',
            'data' => [
                'id' => Crypt::encryptString((string) $student->id),
                'student_no' => $student->student_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id): JsonResponse
    {
        $student = $this->resolveStudent($id);

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student removed successfully.',
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Section Student Search
    |--------------------------------------------------------------------------
    */

    public function search(Request $request, string $id): JsonResponse
    {
        $section = $this->resolveSection($id);

        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $alreadyEnrolledIds = Enrollment::where(
            'section_id',
            $section->id
        )->pluck('student_id');

        $students = Student::whereNotIn('id', $alreadyEnrolledIds)
            ->where(function ($query) use ($q) {
                $query->where('student_no', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get([
                'id',
                'student_no',
                'first_name',
                'last_name',
                'course',
                'year_level',
            ]);

        $students = $students->map(function ($student) {
            return [
                'id' => Crypt::encryptString((string) $student->id),
                'student_no' => $student->student_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $students,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Enroll Student
    |--------------------------------------------------------------------------
    */

    public function enroll(Request $request, string $id): JsonResponse
    {
        $section = $this->resolveSection($id);

        $validated = $request->validate([
            'student_no' => 'required|string|exists:students,student_no',
        ]);

        $student = Student::where(
            'student_no',
            $validated['student_no']
        )->firstOrFail();

        $alreadyEnrolled = Enrollment::where('student_id', $student->id)
            ->where('section_id', $section->id)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'success' => false,
                'message' => "{$student->first_name} {$student->last_name} is already enrolled in this section.",
                'errors' => [
                    'student_no' => [
                        "{$student->first_name} {$student->last_name} is already enrolled in this section.",
                    ],
                ],
            ], 422);
        }

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'semester_id' => $section->semester_id,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$student->first_name} {$student->last_name} enrolled successfully.",
            'data' => [
                'id' => Crypt::encryptString((string) $enrollment->id),
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Enroll
    |--------------------------------------------------------------------------
    */

    public function bulkEnroll(Request $request, string $id): JsonResponse
    {
        $section = $this->resolveSection($id);

        $validated = $request->validate([
            'student_nos' => 'required|string',
        ]);

        $lines = preg_split(
            '/[\r\n,;]+/',
            $validated['student_nos']
        );

        $lines = array_filter(
            array_map('trim', $lines)
        );

        $enrolled = 0;
        $skipped = [];
        $notFound = [];

        foreach ($lines as $no) {
            $student = Student::where(
                'student_no',
                $no
            )->first();

            if (! $student) {
                $notFound[] = $no;
                continue;
            }

            $alreadyIn = Enrollment::where(
                'student_id',
                $student->id
            )
                ->where('section_id', $section->id)
                ->exists();

            if ($alreadyIn) {
                $skipped[] = $no;
                continue;
            }

            Enrollment::create([
                'student_id' => $student->id,
                'section_id' => $section->id,
                'semester_id' => $section->semester_id,
                'status' => 'active',
            ]);

            $enrolled++;
        }

        $message = "{$enrolled} student(s) enrolled.";

        if ($skipped) {
            $message .= ' Already enrolled: '
                . implode(', ', $skipped)
                . '.';
        }

        if ($notFound) {
            $message .= ' Not found: '
                . implode(', ', $notFound)
                . '.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'enrolled' => $enrolled,
                'skipped' => $skipped,
                'not_found' => $notFound,
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Unenroll
    |--------------------------------------------------------------------------
    */

    public function unenroll(string $id): JsonResponse
    {
        $enrollment = $this->resolveEnrollment($id);

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student removed from section successfully.',
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Encrypted Student ID
    |--------------------------------------------------------------------------
    */

    private function resolveStudent(string $id): Student
    {
        try {
            $studentId = Crypt::decryptString($id);

            return Student::findOrFail($studentId);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Encrypted Section ID
    |--------------------------------------------------------------------------
    */

    private function resolveSection(string $id): Section
    {
        try {
            $sectionId = Crypt::decryptString($id);

            return Section::findOrFail($sectionId);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Encrypted Enrollment ID
    |--------------------------------------------------------------------------
    */

    private function resolveEnrollment(string $id): Enrollment
    {
        try {
            $enrollmentId = Crypt::decryptString($id);

            return Enrollment::findOrFail($enrollmentId);
        } catch (DecryptException $e) {
            abort(404);
        }
    }
}

