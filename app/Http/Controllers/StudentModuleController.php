<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\GradingComponent;
use App\Models\GradingItemScore;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\Section;
use App\Models\Semester;
use App\Models\TransmutationScale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentModuleController extends Controller
{
    private function resolveStudent(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->student) {
            return $user->student;
        }

        // If a student record exists with the same email, auto-link it to this user.
        // This supports flows where admins pre-create students, then create user accounts separately.
        $student = \App\Models\Student::where('email', $user->email)->first();

        if (! $student) {
            abort(403, 'No student profile linked to this account.');
        }

        if ($student->user_id && (int) $student->user_id !== (int) $user->id) {
            abort(403, 'Student profile is already linked to another account.');
        }

        $student->forceFill(['user_id' => $user->id])->save();

        return $student->fresh();
    }

    public function dashboard(Request $request): Response
    {
        $student = $this->resolveStudent($request);

        $enrollments = $student->enrollments()
            ->with(['section.subject', 'section.semester', 'semester'])
            ->where('status', 'active')
            ->get();

        $sectionIds = $enrollments->pluck('section_id');

        // Upcoming assignments due in next 14 days, not yet submitted
        $submittedIds = $student->submissions()->pluck('assignment_id');
        $upcoming = Assignment::whereIn('section_id', $sectionIds)
            ->where('is_published', true)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(14))
            ->whereNotIn('id', $submittedIds)
            ->with('section.subject')
            ->orderBy('due_date')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'due_date' => $a->due_date,
                'section_name' => $a->section->name,
                'subject_code' => $a->section->subject->code,
                'section_id' => $a->section_id,
            ]);

        // Recently released grades (last 7 days)
        $recentGrades = $student->grades()
            ->where('is_released', true)
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereNotNull('assignment_id')
            ->with(['assignment.section.subject'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($g) => [
                'raw_score' => $g->raw_score,
                'max_score' => $g->max_score,
                'assignment_title' => $g->assignment?->title,
                'subject_code' => $g->assignment?->section?->subject?->code,
                'section_id' => $g->assignment?->section_id,
            ]);

        return Inertia::render('student/Dashboard', [
            'student' => $student,
            'enrollments' => $enrollments,
            'upcoming' => $upcoming,
            'recentGrades' => $recentGrades,
        ]);
    }

    public function sectionModules(Request $request, Section $section): Response
    {
        $student = $this->resolveStudent($request);

        $enrolled = $student->enrollments()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this section.');
        }

        $section->load(['subject', 'semester', 'faculty']);

        $modules = $section->modules()
            ->where('is_published', true)
            ->with(['files'])
            ->get()
            ->map(function (Module $module) use ($student) {
                $module->is_read = $module->isReadByStudent($student->id);
                return $module;
            });

        $readCount = $modules->filter(fn ($m) => $m->is_read)->count();

        return Inertia::render('student/SectionModules', [
            'section' => $section,
            'modules' => $modules,
            'progress' => [
                'read' => $readCount,
                'total' => $modules->count(),
            ],
        ]);
    }

    public function viewModule(Request $request, Section $section, Module $module): Response
    {
        $student = $this->resolveStudent($request);

        // Ensure the module actually belongs to the section in the URL
        if ((int) $module->section_id !== (int) $section->id) {
            abort(404);
        }

        if (! $module->is_published) {
            abort(404);
        }

        $enrolled = $student->enrollments()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->exists();

        if (! $enrolled) {
            abort(403);
        }

        $module->load(['files', 'section.subject', 'section.semester']);
        $isRead = $module->isReadByStudent($student->id);

        return Inertia::render('student/ModuleView', [
            'module' => $module,
            'isRead' => $isRead,
        ]);
    }

    public function grades(Request $request, Section $section): Response
    {
        $student = $this->resolveStudent($request);

        $enrolled = $student->enrollments()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this section.');
        }

        $section->load(['subject', 'semester']);

        $components = GradingComponent::where('section_id', $section->id)
            ->with(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('order')])
            ->orderBy('order')
            ->get()
            ->map(function ($component) use ($student) {
                $items = $component->items->map(function ($item) use ($student) {
                    $scoreRecord = GradingItemScore::where('grading_item_id', $item->id)
                        ->where('student_id', $student->id)
                        ->where('is_released', true)
                        ->first();

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'max_score' => $item->max_score,
                        'score' => $scoreRecord?->score,
                        'is_released' => $scoreRecord !== null,
                    ];
                });

                $releasedItems = $items->filter(fn ($i) => $i['is_released']);
                $earned = $releasedItems->sum('score');
                $total = $releasedItems->sum('max_score');
                $percentage = $total > 0 ? round(($earned / $total) * 100, 2) : null;

                return [
                    'id' => $component->id,
                    'name' => $component->name,
                    'weight' => $component->weight_percentage,
                    'items' => $items->values(),
                    'earned' => $releasedItems->count() > 0 ? $earned : null,
                    'total' => $total > 0 ? $total : null,
                    'percentage' => $percentage,
                    'weighted' => $percentage !== null
                        ? round($percentage * ($component->weight_percentage / 100), 2)
                        : null,
                ];
            });

        $weightedTotal = $components
            ->filter(fn ($c) => $c['weighted'] !== null)
            ->sum('weighted');

        $allHaveWeighted = $components->every(fn ($c) => $c['weighted'] !== null);

        $transmutedGrade = $allHaveWeighted && $components->isNotEmpty()
            ? TransmutationScale::transmute($weightedTotal, $section->id)
            : null;

        return Inertia::render('student/Grades', [
            'section' => $section,
            'components' => $components,
            'weightedTotal' => $allHaveWeighted && $components->isNotEmpty() ? round($weightedTotal, 2) : null,
            'transmutedGrade' => $transmutedGrade,
        ]);
    }

    public function browseSections(Request $request): Response
    {
        $student = $this->resolveStudent($request);

        $activeSemester = Semester::getActive();

        $enrolledSectionIds = $student->enrollments()
            ->where('status', 'active')
            ->pluck('section_id');

        $query = Section::with(['subject', 'semester', 'faculty'])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')]);

        if ($activeSemester) {
            $query->where('semester_id', $activeSemester->id);
        }

        $sections = $query->latest()->get()->map(fn ($s) => [
            'id'               => $s->id,
            'name'             => $s->name,
            'schedule'         => $s->schedule,
            'subject_code'     => $s->subject->code,
            'subject_name'     => $s->subject->name,
            'faculty_name'     => $s->faculty->name,
            'semester'         => $s->semester->name.' '.$s->semester->school_year,
            'enrollments_count' => $s->enrollments_count,
            'is_enrolled'      => $enrolledSectionIds->contains($s->id),
        ]);

        return Inertia::render('student/BrowseSections', [
            'sections'       => $sections,
            'activeSemester' => $activeSemester,
        ]);
    }

    public function selfEnroll(Request $request, Section $section): RedirectResponse
    {
        $student = $this->resolveStudent($request);

        $alreadyEnrolled = Enrollment::where('student_id', $student->id)
            ->where('section_id', $section->id)
            ->exists();

        if ($alreadyEnrolled) {
            return back()->withErrors(['enroll' => 'You are already enrolled in this section.']);
        }

        Enrollment::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'semester_id' => $section->semester_id,
            'status'     => 'active',
        ]);

        return redirect()->route('student.dashboard')
            ->with('success', 'You have enrolled in '.$section->name.'.');
    }

    public function markRead(Request $request, Module $module): RedirectResponse
    {
        $student = $this->resolveStudent($request);

        if (! $module->is_published) {
            abort(404);
        }

        ModuleProgress::firstOrCreate([
            'student_id' => $student->id,
            'module_id' => $module->id,
        ]);

        return back()->with('success', 'Module marked as read.');
    }
}
