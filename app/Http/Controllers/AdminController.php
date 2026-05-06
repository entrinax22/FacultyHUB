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

class AdminController extends Controller
{
    public function dashboard(): Response
    {
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
        ])->orderByDesc('created_at')->limit(6)->get();

        $recentSections = Section::with(['subject', 'semester', 'faculty'])
            ->withCount('enrollments')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'subject_code' => $s->subject->code,
                'faculty_name' => $s->faculty->name,
                'semester' => $s->semester->name . ' ' . $s->semester->school_year,
                'enrollments_count' => $s->enrollments_count,
            ]);

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => $totalUsers,
                'faculty' => $totalFaculty,
                'students' => $totalStudents,
                'sections' => $totalSections,
                'enrollments' => $totalEnrollments,
                'submissions' => $totalSubmissions,
                'pendingGrading' => $pendingGrading,
            ],
            'activeSemester' => $activeSemester,
            'semesterStats' => $semesterStats,
            'recentSections' => $recentSections,
        ]);
    }

    public function users(Request $request): Response
    {
        $query = User::query();

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('role')->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'created_at' => $u->created_at->toDateString(),
                'email_verified_at' => $u->email_verified_at?->toDateString(),
            ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,faculty,student',
        ]);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Role updated to {$validated['role']}.");
    }

    public function reports(): Response
    {
        // Enrollment by semester
        $enrollmentBySemester = Semester::withCount([
            'sections',
            'enrollments' => fn ($q) => $q->where('status', 'active'),
        ])->orderBy('created_at')->get()->map(fn ($s) => [
            'label' => $s->name . ' ' . $s->school_year,
            'sections' => $s->sections_count,
            'enrollments' => $s->enrollments_count,
        ]);

        // Submission completion rates by assignment type
        $submissionRates = Assignment::selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->get()
            ->map(function ($row) {
                $submitted = Submission::whereHas('assignment', fn ($q) => $q->where('type', $row->type))->count();
                return [
                    'type' => $row->type,
                    'assignments' => $row->total,
                    'submissions' => $submitted,
                ];
            });

        // Grade distribution (released grades)
        $gradeDistribution = Grade::where('is_released', true)
            ->whereNotNull('raw_score')
            ->whereNotNull('max_score')
            ->get()
            ->map(fn ($g) => $g->max_score > 0 ? round(($g->raw_score / $g->max_score) * 100) : 0)
            ->groupBy(fn ($pct) => match (true) {
                $pct >= 90 => '90–100',
                $pct >= 80 => '80–89',
                $pct >= 75 => '75–79',
                $pct >= 60 => '60–74',
                default    => 'Below 60',
            })
            ->map(fn ($group, $label) => ['label' => $label, 'count' => $group->count()])
            ->values();

        // Top sections by enrollment
        $topSections = Section::with(['subject', 'semester', 'faculty'])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('enrollments_count')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'subject' => $s->subject->code . ' — ' . $s->subject->name,
                'faculty' => $s->faculty->name,
                'semester' => $s->semester->name . ' ' . $s->semester->school_year,
                'enrollments' => $s->enrollments_count,
            ]);

        // System totals
        $totals = [
            'users'       => User::count(),
            'sections'    => Section::count(),
            'enrollments' => Enrollment::where('status', 'active')->count(),
            'modules'     => Module::count(),
            'assignments' => Assignment::count(),
            'submissions' => Submission::count(),
            'graded'      => Grade::where('is_released', true)->count(),
        ];

        return Inertia::render('admin/Reports', [
            'enrollmentBySemester' => $enrollmentBySemester,
            'submissionRates' => $submissionRates,
            'gradeDistribution' => $gradeDistribution,
            'topSections' => $topSections,
            'totals' => $totals,
        ]);
    }
}
