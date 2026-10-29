<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AiProcessingRun;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Module;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('admin/Dashboard');
    }
    public function dashboardData(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'semester_id' => ['nullable', 'string'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);
            $activeSemester = Semester::getActive();

            $semesterId = $activeSemester?->id;
            if (! empty($filters['semester_id'])) {
                try {
                    $semesterId = (int) Crypt::decryptString($filters['semester_id']);
                } catch (\Throwable) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid semester filter.',
                        'data' => null,
                    ], 422);
                }
            }

            $dateFrom = $filters['date_from'] ?? null;
            $dateTo = $filters['date_to'] ?? null;
            $dateFilter = static function ($query, string $column) use ($dateFrom, $dateTo): void {
                $query->when($dateFrom, fn ($q) => $q->whereDate($column, '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate($column, '<=', $dateTo));
            };

            $usersByRole = User::query()
                ->select('role', DB::raw('COUNT(*) as aggregate'))
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->groupBy('role')->pluck('aggregate', 'role');

            $sectionQuery = Section::query()->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId));
            $dateFilter($sectionQuery, 'created_at');
            $sectionIds = $sectionQuery->pluck('id');
            $enrollmentQuery = Enrollment::query()->where('status', 'active')->whereIn('section_id', $sectionIds);
            $dateFilter($enrollmentQuery, 'created_at');
            $enrollmentsBySection = $enrollmentQuery->select('section_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('section_id')->pluck('aggregate', 'section_id');

            $assignmentQuery = Assignment::query()->whereIn('section_id', $sectionIds);
            $dateFilter($assignmentQuery, 'created_at');
            $assignmentIds = (clone $assignmentQuery)->pluck('assignments.id');
            $assignmentsBySection = $assignmentQuery->select('section_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('section_id')->pluck('aggregate', 'section_id');

            $submissionQuery = Submission::query()->whereIn('assignment_id', $assignmentIds);
            $dateFilter($submissionQuery, 'submitted_at');
            $completedStatuses = ['grading', 'graded', 'approved'];
            $completedSubmissions = (clone $submissionQuery)->whereIn('status', $completedStatuses)->count();
            $totalSubmissionSlots = $sectionIds->sum(fn ($id) =>
                (int) ($enrollmentsBySection[$id] ?? 0) * (int) ($assignmentsBySection[$id] ?? 0));
            $submissionCompletionRate = $totalSubmissionSlots > 0
                ? round(($completedSubmissions / $totalSubmissionSlots) * 100, 1)
                : 0.0;

            $submissionStatuses = (clone $submissionQuery)
                ->select('status', DB::raw('COUNT(*) as aggregate'))->groupBy('status')
                ->pluck('aggregate', 'status');
            $submissionTrend = (clone $submissionQuery)
                ->selectRaw('CAST(submitted_at AS DATE) as day, COUNT(*) as aggregate')
                ->groupBy('day')->orderBy('day')->limit(90)->get()
                ->map(fn ($row) => ['date' => (string) $row->day, 'count' => (int) $row->aggregate])->values();

            $releasedGrades = Grade::query()->where('grades.is_released', true)->whereIn('grades.section_id', $sectionIds)
                ->whereNotNull('grades.raw_score')->whereNotNull('grades.max_score')->where('grades.max_score', '>', 0);
            $releasedGrades->when($dateFrom, fn ($q) => $q->whereDate('grades.updated_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('grades.updated_at', '<=', $dateTo));
            $roundedPercentage = 'ROUND((grades.raw_score * 100.0) / grades.max_score)';
            $gradeBand = "CASE WHEN {$roundedPercentage} >= 90 THEN '90-100' WHEN {$roundedPercentage} >= 80 THEN '80-89' WHEN {$roundedPercentage} >= 75 THEN '75-79' WHEN {$roundedPercentage} >= 60 THEN '60-74' ELSE 'Below 60' END";
            $gradeDistribution = (clone $releasedGrades)->selectRaw("{$gradeBand} as label, COUNT(*) as aggregate")
                ->groupByRaw($gradeBand)->orderByRaw('MIN(grades.raw_score / grades.max_score) DESC')->get()
                ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->aggregate])->values();

            $facultyActivity = (clone $releasedGrades)->join('sections', 'sections.id', '=', 'grades.section_id')
                ->select('sections.faculty_id', DB::raw('COUNT(*) as aggregate'))->groupBy('sections.faculty_id')
                ->orderByDesc('aggregate')->limit(10)->get()->values()->map(fn ($row, $index) => [
                    'label' => 'Faculty '.($index + 1), 'count' => (int) $row->aggregate,
                ]);

            $aiRuns = AiProcessingRun::query()->whereIn('resource_id', $assignmentIds)
                ->where('resource_type', Assignment::class);
            $dateFilter($aiRuns, 'created_at');
            $aiByStatus = (clone $aiRuns)->select('status', DB::raw('COUNT(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
            $aiOutcomes = (clone $aiRuns)->select('processing_type', 'status', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('processing_type', 'status')->get()->map(fn ($row) => [
                    'type' => $row->processing_type, 'status' => $row->status, 'count' => (int) $row->aggregate,
                ])->values();
            $stalledAiRuns = (clone $aiRuns)->whereIn('status', [AiProcessingRun::STATUS_PENDING, AiProcessingRun::STATUS_PROCESSING])
                ->where('created_at', '<', now()->subMinutes(30))->count();

            $completedBySection = Submission::query()
                ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
                ->whereIn('submissions.assignment_id', $assignmentIds)
                ->whereIn('submissions.status', $completedStatuses)
                ->when($dateFrom, fn ($q) => $q->whereDate('submissions.submitted_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('submissions.submitted_at', '<=', $dateTo))
                ->select('assignments.section_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('assignments.section_id')->pluck('aggregate', 'section_id');
            $lowParticipationSections = $sectionIds->filter(function ($id) use ($enrollmentsBySection, $assignmentsBySection, $completedBySection) {
                $expected = (int) ($enrollmentsBySection[$id] ?? 0) * (int) ($assignmentsBySection[$id] ?? 0);
                return $expected > 0 && ((int) ($completedBySection[$id] ?? 0) / $expected) < 0.5;
            })->count();

            $alerts = [];
            if ($totalSubmissionSlots > 0 && $submissionCompletionRate < 70) {
                $alerts[] = ['type' => 'warning', 'title' => 'Low submission completion', 'detail' => "Completion is {$submissionCompletionRate}% (target: 70%)."];
            }
            $pendingGrading = (clone $submissionQuery)->where('status', 'grading')->count();
            if ($pendingGrading > 0) {
                $alerts[] = ['type' => 'warning', 'title' => 'Pending grading workload', 'detail' => "{$pendingGrading} submissions are awaiting grading."];
            }
            $failedAiRuns = (int) ($aiByStatus[AiProcessingRun::STATUS_FAILED] ?? 0);
            if ($failedAiRuns > 0) {
                $alerts[] = ['type' => 'error', 'title' => 'Failed AI processing', 'detail' => "{$failedAiRuns} AI processing runs failed."];
            }
            if ($stalledAiRuns > 0) {
                $alerts[] = ['type' => 'warning', 'title' => 'Stalled processing runs', 'detail' => "{$stalledAiRuns} AI runs have been active for more than 30 minutes."];
            }
            if ($lowParticipationSections > 0) {
                $alerts[] = ['type' => 'warning', 'title' => 'Low section participation', 'detail' => "{$lowParticipationSections} sections are below 50% submission participation."];
            }

            $semesterStats = Semester::withCount(['sections', 'enrollments' => fn ($q) => $q->where('status', 'active')])
                ->orderByDesc('created_at')->limit(6)->get()->map(fn ($semester) => [
                    'id' => Crypt::encryptString((string) $semester->id), 'name' => $semester->name, 'school_year' => $semester->school_year,
                    'is_active' => (bool) $semester->is_active, 'sections_count' => $semester->sections_count, 'enrollments_count' => $semester->enrollments_count,
                ])->values();

            $semesters = Semester::query()->orderByDesc('start_date')->limit(20)->get()->map(fn ($semester) => [
                'id' => Crypt::encryptString((string) $semester->id), 'label' => $semester->name.' '.$semester->school_year,
            ])->values();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => [
                    'stats' => [
                        'users' => (int) $usersByRole->sum(),
                        'usersByRole' => $usersByRole->map(fn ($value) => (int) $value)->all(),
                        'faculty' => (int) ($usersByRole['faculty'] ?? 0),
                        'students' => (int) ($usersByRole['student'] ?? 0),
                        'sections' => $sectionIds->count(),
                        'enrollments' => (int) $enrollmentsBySection->sum(),
                        'submissions' => (int) $submissionQuery->count(),
                        'pendingGrading' => $pendingGrading,
                        'submissionCompletionRate' => $submissionCompletionRate,
                        'aiProcessing' => (int) $aiRuns->count(),
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
                    'semesters' => $semesters,
                    'submissionTrend' => $submissionTrend,
                    'submissionStatuses' => $submissionStatuses->map(fn ($value, $key) => ['label' => $key, 'count' => (int) $value])->values(),
                    'gradeDistribution' => $gradeDistribution,
                    'facultyActivity' => $facultyActivity,
                    'aiOutcomes' => $aiOutcomes,
                    'alerts' => $alerts,
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

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $userId = $this->decryptUserId($id);

            if ($userId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid user ID.',
                    'data' => null,
                ], 400);
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            ]);

            $user = User::find($userId);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User profile updated successfully.',
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
                'message' => 'Failed to update user profile.',
                'data' => null,
            ], 500);
        }
    }

    private function decryptUserId(string $id): ?int
    {
        try {
            return (int) Crypt::decryptString($id);
        } catch (\Throwable) {
            return null;
        }
    }

    public function reports_index(){
        return Inertia::render('admin/ReportsIndex');
    }

    public function reports(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'semester_id' => ['nullable', 'string'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);
            $semesterId = null;
            if (! empty($filters['semester_id'])) {
                try {
                    $semesterId = (int) Crypt::decryptString($filters['semester_id']);
                } catch (\Throwable) {
                    return response()->json(['success' => false, 'message' => 'Invalid semester filter.', 'data' => null], 422);
                }
            }
            $dateFrom = $filters['date_from'] ?? null;
            $dateTo = $filters['date_to'] ?? null;
            $dateFilter = static function ($query, string $column) use ($dateFrom, $dateTo): void {
                $query->when($dateFrom, fn ($q) => $q->whereDate($column, '>=', $dateFrom))->when($dateTo, fn ($q) => $q->whereDate($column, '<=', $dateTo));
            };

            $sectionQuery = Section::query()->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId));
            $dateFilter($sectionQuery, 'sections.created_at');
            $sectionIds = $sectionQuery->pluck('sections.id');
            $enrollmentQuery = Enrollment::query()->where('status', 'active')->whereIn('section_id', $sectionIds);
            $dateFilter($enrollmentQuery, 'enrollments.created_at');
            $enrollmentsBySection = (clone $enrollmentQuery)->select('section_id', DB::raw('COUNT(*) as aggregate'))->groupBy('section_id')->pluck('aggregate', 'section_id');

            $assignmentQuery = Assignment::query()->whereIn('section_id', $sectionIds);
            $dateFilter($assignmentQuery, 'assignments.created_at');
            $assignmentIds = (clone $assignmentQuery)->pluck('assignments.id');
            $submittedQuery = Submission::query()->whereIn('assignment_id', $assignmentIds);
            $dateFilter($submittedQuery, 'submissions.submitted_at');
            $submittedByAssignment = (clone $submittedQuery)->select('assignment_id', DB::raw('COUNT(*) as aggregate'))->groupBy('assignment_id')->pluck('aggregate', 'assignment_id');
            $assignmentAnalysis = (clone $assignmentQuery)->select('assignments.id', 'assignments.title', 'assignments.section_id')->orderBy('assignments.title')->limit(50)->get()->map(function ($assignment) use ($enrollmentsBySection, $submittedByAssignment) {
                $expected = (int) ($enrollmentsBySection[$assignment->section_id] ?? 0);
                $submitted = (int) ($submittedByAssignment[$assignment->id] ?? 0);
                return ['id' => Crypt::encryptString((string) $assignment->id), 'title' => $assignment->title, 'submitted' => $submitted, 'expected' => $expected, 'completion_rate' => $expected > 0 ? round(($submitted / $expected) * 100, 1) : null];
            })->values();

            $semesters = Semester::query()->when($semesterId, fn ($q) => $q->whereKey($semesterId))->orderBy('start_date')->get(['id', 'name', 'school_year']);
            $semesterSections = Section::whereIn('semester_id', $semesters->pluck('id'))->select('semester_id', DB::raw('COUNT(*) as aggregate'))->groupBy('semester_id')->pluck('aggregate', 'semester_id');
            $semesterEnrollments = Enrollment::where('status', 'active')->whereIn('semester_id', $semesters->pluck('id'))->when($dateFrom, fn ($q) => $q->whereDate('enrollments.created_at', '>=', $dateFrom))->when($dateTo, fn ($q) => $q->whereDate('enrollments.created_at', '<=', $dateTo))->select('semester_id', DB::raw('COUNT(*) as aggregate'))->groupBy('semester_id')->pluck('aggregate', 'semester_id');
            $enrollmentBySemester = $semesters->map(fn ($semester) => ['label' => $semester->name.' '.$semester->school_year, 'sections' => (int) ($semesterSections[$semester->id] ?? 0), 'enrollments' => (int) ($semesterEnrollments[$semester->id] ?? 0)])->values();

            $roundedPercentage = 'CASE WHEN grades.max_score > 0 THEN ROUND((grades.raw_score * 100.0) / grades.max_score) ELSE 0 END';
            $gradeBand = "CASE WHEN {$roundedPercentage} >= 90 THEN '90–100' WHEN {$roundedPercentage} >= 80 THEN '80–89' WHEN {$roundedPercentage} >= 75 THEN '75–79' WHEN {$roundedPercentage} >= 60 THEN '60–74' ELSE 'Below 60' END";
            $gradeQuery = Grade::where('grades.is_released', true)->whereIn('grades.section_id', $sectionIds)->whereNotNull('grades.raw_score')->whereNotNull('grades.max_score');
            $dateFilter($gradeQuery, 'grades.updated_at');
            $gradeDistribution = (clone $gradeQuery)->selectRaw("{$gradeBand} as label, COUNT(*) as count")->groupByRaw($gradeBand)->orderByRaw('MIN(grades.id)')->get()->map(fn ($band) => ['label' => $band->label, 'count' => (int) $band->count])->values();

            $topSections = Section::with(['subject', 'semester'])->whereIn('sections.id', $sectionIds)->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')->when($dateFrom, fn ($query) => $query->whereDate('enrollments.created_at', '>=', $dateFrom))->when($dateTo, fn ($query) => $query->whereDate('enrollments.created_at', '<=', $dateTo))])->orderByDesc('enrollments_count')->limit(10)->get()->map(fn ($section) => ['id' => Crypt::encryptString((string) $section->id), 'name' => $section->name, 'subject' => $section->subject ? $section->subject->code.' — '.$section->subject->name : null, 'semester' => $section->semester ? $section->semester->name.' '.$section->semester->school_year : null, 'enrollments' => (int) $section->enrollments_count])->values();

            $userQuery = User::query();
            $dateFilter($userQuery, 'users.created_at');
            $moduleQuery = Module::query()->whereIn('section_id', $sectionIds);
            $dateFilter($moduleQuery, 'modules.created_at');
            $totals = ['users' => (int) $userQuery->count(), 'sections' => $sectionIds->count(), 'enrollments' => (int) $enrollmentQuery->count(), 'modules' => (int) $moduleQuery->count(), 'assignments' => (int) (clone $assignmentQuery)->count(), 'submissions' => (int) (clone $submittedQuery)->count(), 'graded' => (int) (clone $gradeQuery)->count()];
            $insights = $assignmentAnalysis->filter(fn ($row) => $row['expected'] > 0 && $row['completion_rate'] < 50)->take(5)->map(fn ($row) => ['type' => 'warning', 'title' => 'Low assignment completion', 'detail' => $row['title'].' is at '.$row['completion_rate'].'% (threshold: 50%).'])->values();

            $semesterOptions = Semester::query()->orderByDesc('start_date')->limit(20)->get()->map(fn ($semester) => ['id' => Crypt::encryptString((string) $semester->id), 'label' => $semester->name.' '.$semester->school_year])->values();
            return response()->json(['success' => true, 'message' => 'Reports retrieved successfully.', 'data' => ['semesters' => $semesterOptions, 'enrollmentBySemester' => $enrollmentBySemester, 'assignmentAnalysis' => $assignmentAnalysis, 'submissionRates' => [], 'gradeDistribution' => $gradeDistribution, 'topSections' => $topSections, 'insights' => $insights, 'totals' => $totals]], 200);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve reports.', 'data' => ['enrollmentBySemester' => [], 'assignmentAnalysis' => [], 'submissionRates' => [], 'gradeDistribution' => [], 'topSections' => [], 'insights' => [], 'totals' => ['users' => 0, 'sections' => 0, 'enrollments' => 0, 'modules' => 0, 'assignments' => 0, 'submissions' => 0, 'graded' => 0]]], 500);
        }
    }

    private function legacyReports(Request $request): JsonResponse
    {
        try {
            // Enrollment by semester
            $enrollmentBySemester = Semester::withCount([
                'sections',
                'enrollments' => fn ($q) => $q->where('status', 'active'),
            ])
                ->orderBy('created_at')
                ->cursor()
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
            $roundedPercentage = 'CASE WHEN max_score > 0 THEN ROUND((raw_score * 100.0) / max_score) ELSE 0 END';
            $gradeBand = "CASE
                WHEN {$roundedPercentage} >= 90 THEN '90–100'
                WHEN {$roundedPercentage} >= 80 THEN '80–89'
                WHEN {$roundedPercentage} >= 75 THEN '75–79'
                WHEN {$roundedPercentage} >= 60 THEN '60–74'
                ELSE 'Below 60'
            END";

            $gradeDistribution = Grade::where('is_released', true)
                ->whereNotNull('raw_score')
                ->whereNotNull('max_score')
                ->selectRaw("{$gradeBand} as label, COUNT(*) as count")
                ->groupByRaw($gradeBand)
                ->orderByRaw('MIN(id)')
                ->get()
                ->map(fn ($band) => [
                    'label' => $band->label,
                    'count' => (int) $band->count,
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
