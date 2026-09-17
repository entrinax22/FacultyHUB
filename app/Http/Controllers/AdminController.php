<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Module;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('admin/Dashboard');
    }
    public function dashboardData(): JsonResponse
    {
        try {
            $totalUsers = User::count();

            $totalFaculty = User::where('role', 'faculty')->count();

            $totalStudents = Student::count();

            $totalSections = Section::count();

            $totalEnrollments = Enrollment::where('status', 'active')->count();

            $totalSubmissions = Submission::count();

            $pendingGrading = Submission::where('status', 'grading')->count();

            $activeSemester = Semester::getActive();

            $semesterStats = Semester::withCount([
                'sections',
                'enrollments' => fn ($q) => $q->where('status', 'active'),
            ])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(fn ($semester) => [
                    'id' => Crypt::encryptString((string) $semester->id),
                    'name' => $semester->name,
                    'school_year' => $semester->school_year,
                    'label' => $semester->name . ' ' . $semester->school_year,
                    'sections_count' => $semester->sections_count,
                    'enrollments_count' => $semester->enrollments_count,
                ])
                ->values();

            $recentSections = Section::with([
                'subject',
                'semester',
                'faculty',
            ])
                ->withCount('enrollments')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($section) => [
                    'id' => Crypt::encryptString((string) $section->id),
                    'name' => $section->name,
                    'subject_code' => $section->subject?->code,
                    'subject_name' => $section->subject?->name,
                    'faculty_name' => $section->faculty?->name,
                    'semester' => $section->semester
                        ? $section->semester->name . ' ' . $section->semester->school_year
                        : null,
                    'enrollments_count' => $section->enrollments_count,
                ])
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => [
                    'stats' => [
                        'users' => $totalUsers,
                        'faculty' => $totalFaculty,
                        'students' => $totalStudents,
                        'sections' => $totalSections,
                        'enrollments' => $totalEnrollments,
                        'submissions' => $totalSubmissions,
                        'pendingGrading' => $pendingGrading,
                    ],

                    'activeSemester' => $activeSemester
                        ? [
                            'id' => Crypt::encryptString(
                                (string) $activeSemester->id
                            ),
                            'name' => $activeSemester->name,
                            'school_year' => $activeSemester->school_year,
                        ]
                        : null,

                    'semesterStats' => $semesterStats,

                    'recentSections' => $recentSections,
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data.',
                'data' => [
                    'stats' => [
                        'users' => 0,
                        'faculty' => 0,
                        'students' => 0,
                        'sections' => 0,
                        'enrollments' => 0,
                        'submissions' => 0,
                        'pendingGrading' => 0,
                    ],
                    'activeSemester' => null,
                    'semesterStats' => [],
                    'recentSections' => [],
                ],
            ], 500);
        }
    }

    public function index(){
        return Inertia::render('admin/UsersIndex');
    }

    public function users(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Role filter
            if ($role = $request->get('role')) {
                $query->where('role', $role);
            }

            // Search filter
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Page controls
            $perPage = min(
                max($request->integer('per_page', 20), 1),
                100
            );

            $page = max(
                $request->integer('page', 1),
                1
            );

            $users = $query
                ->orderBy('role')
                ->orderBy('name')
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            // Transform user data
            $users->through(fn ($user) => [
                'id' => Crypt::encryptString((string) $user->id),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at?->toDateString(),
                'email_verified_at' => $user->email_verified_at?->toDateString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully.',

                'data' => $users->items(),

                // Page control
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),

                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),

                    'has_more_pages' => $users->hasMorePages(),

                    'next_page_url' => $users->nextPageUrl(),
                    'previous_page_url' => $users->previousPageUrl(),

                    'first_page_url' => $users->url(1),
                    'last_page_url' => $users->url($users->lastPage()),
                ],

                // Current filters
                'filters' => [
                    'role' => $request->get('role'),
                    'search' => $request->get('search'),
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users.',
                'data' => [],
                'pagination' => null,
            ], 500);
        }
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        try {
            // Validate the role
            $validated = $request->validate([
                'role' => 'required|in:admin,faculty,student',
            ]);

            // Decrypt the user ID
            try {
                $userId = Crypt::decryptString($id);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid user ID.',
                    'data' => null,
                ], 400);
            }

            // Find the user
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            // Prevent admin from changing their own role
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own role.',
                    'data' => null,
                ], 422);
            }

            // Update role
            $user->update([
                'role' => $validated['role'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Role updated to {$validated['role']}.",
                'data' => [
                    'id' => Crypt::encryptString((string) $user->id),
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at?->toDateString(),
                    'email_verified_at' => $user->email_verified_at?->toDateString(),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role.',
                'data' => null,
            ], 500);
        }
    }

    public function reports_index(){
        return Inertia::render('admin/ReportsIndex');
    }

    public function reports(Request $request): JsonResponse
    {
        try {
            // Enrollment by semester
            $enrollmentBySemester = Semester::withCount([
                'sections',
                'enrollments' => fn ($q) => $q->where('status', 'active'),
            ])
                ->orderBy('created_at')
                ->get()
                ->map(fn ($semester) => [
                    'label' => $semester->name . ' ' . $semester->school_year,
                    'sections' => $semester->sections_count,
                    'enrollments' => $semester->enrollments_count,
                ])
                ->values();

            // Submission completion rates by assignment type
            $submissionRates = Assignment::selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->get()
                ->map(function ($row) {
                    $submitted = Submission::whereHas(
                        'assignment',
                        fn ($q) => $q->where('type', $row->type)
                    )->count();

                    return [
                        'type' => $row->type,
                        'assignments' => (int) $row->total,
                        'submissions' => $submitted,
                    ];
                })
                ->values();

            // Grade distribution
            $gradeDistribution = Grade::where('is_released', true)
                ->whereNotNull('raw_score')
                ->whereNotNull('max_score')
                ->get()
                ->map(fn ($grade) =>
                    $grade->max_score > 0
                        ? round(($grade->raw_score / $grade->max_score) * 100)
                        : 0
                )
                ->groupBy(fn ($percentage) => match (true) {
                    $percentage >= 90 => '90–100',
                    $percentage >= 80 => '80–89',
                    $percentage >= 75 => '75–79',
                    $percentage >= 60 => '60–74',
                    default => 'Below 60',
                })
                ->map(fn ($group, $label) => [
                    'label' => $label,
                    'count' => $group->count(),
                ])
                ->values();

            // Top sections by enrollment
            $topSections = Section::with([
                'subject',
                'semester',
                'faculty',
            ])
                ->withCount([
                    'enrollments' => fn ($q) => $q->where('status', 'active'),
                ])
                ->orderByDesc('enrollments_count')
                ->limit(10)
                ->get()
                ->map(fn ($section) => [
                    'id' => Crypt::encryptString((string) $section->id),
                    'name' => $section->name,
                    'subject' => $section->subject
                        ? $section->subject->code . ' — ' . $section->subject->name
                        : null,
                    'faculty' => $section->faculty?->name,
                    'semester' => $section->semester
                        ? $section->semester->name . ' ' . $section->semester->school_year
                        : null,
                    'enrollments' => $section->enrollments_count,
                ])
                ->values();

            // System totals
            $totals = [
                'users' => User::count(),
                'sections' => Section::count(),
                'enrollments' => Enrollment::where('status', 'active')->count(),
                'modules' => Module::count(),
                'assignments' => Assignment::count(),
                'submissions' => Submission::count(),
                'graded' => Grade::where('is_released', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reports retrieved successfully.',
                'data' => [
                    'enrollmentBySemester' => $enrollmentBySemester,
                    'submissionRates' => $submissionRates,
                    'gradeDistribution' => $gradeDistribution,
                    'topSections' => $topSections,
                    'totals' => $totals,
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reports.',
                'data' => [
                    'enrollmentBySemester' => [],
                    'submissionRates' => [],
                    'gradeDistribution' => [],
                    'topSections' => [],
                    'totals' => [
                        'users' => 0,
                        'sections' => 0,
                        'enrollments' => 0,
                        'modules' => 0,
                        'assignments' => 0,
                        'submissions' => 0,
                        'graded' => 0,
                    ],
                ],
            ], 500);
        }
    }
}
