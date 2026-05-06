<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Section;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isStudent()) {
            return redirect()->route('student.dashboard');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $activeSemester = Semester::getActive();

        $sectionQuery = Section::where('faculty_id', $user->id);

        if ($activeSemester) {
            $sectionQuery->where('semester_id', $activeSemester->id);
        }

        $mySections = $sectionQuery->with(['subject', 'semester'])->withCount('enrollments')->get();

        $sectionIds = $mySections->pluck('id');

        $studentCount = $sectionIds->isEmpty()
            ? 0
            : Enrollment::whereIn('section_id', $sectionIds)->distinct()->count('student_id');

        $stats = [
            'sections' => $mySections->count(),
            'students' => $studentCount,
            'modules' => $sectionIds->isEmpty() ? 0 : Module::whereIn('section_id', $sectionIds)->count(),
            'assignments' => $sectionIds->isEmpty() ? 0 : Assignment::whereIn('section_id', $sectionIds)->count(),
        ];

        return Inertia::render('Dashboard', [
            'activeSemester' => $activeSemester,
            'mySections' => $mySections,
            'stats' => $stats,
        ]);
    }
}
