<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Section::with(['semester', 'subject', 'faculty'])->withCount('enrollments');

        if ($request->user()->isFaculty()) {
            $query->where('faculty_id', $request->user()->id);
        }

        return Inertia::render('sections/Index', [
            'sections' => $query->latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sections/Form', [
            'semesters' => Semester::orderByDesc('is_active')->latest()->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'semester_id' => 'required|exists:semesters,id',
            'subject_id' => 'required|exists:subjects,id',
            'schedule' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:100',
        ]);

        $validated['faculty_id'] = $request->user()->id;

        Section::create($validated);

        return redirect()->route('sections.index')->with('success', 'Section created successfully.');
    }

    public function show(Request $request, Section $section): Response
    {
        $this->authorizeSection($section, $request);
        $section->load(['semester', 'subject', 'faculty']);
        $enrollments = $section->enrollments()->with('student')->get();

        return Inertia::render('sections/Show', [
            'section' => $section,
            'enrollments' => $enrollments,
        ]);
    }

    public function edit(Request $request, Section $section): Response
    {
        $this->authorizeSection($section, $request);

        return Inertia::render('sections/Form', [
            'section' => $section,
            'semesters' => Semester::orderByDesc('is_active')->latest()->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section, $request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'semester_id' => 'required|exists:semesters,id',
            'subject_id' => 'required|exists:subjects,id',
            'schedule' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:100',
        ]);

        $section->update($validated);

        return redirect()->route('sections.index')->with('success', 'Section updated successfully.');
    }

    public function destroy(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section, $request);
        $section->delete();

        return redirect()->route('sections.index')->with('success', 'Section deleted.');
    }

    private function authorizeSection(Section $section, Request $request): void
    {
        if ($request->user()->isFaculty() && $section->faculty_id !== $request->user()->id) {
            abort(403, 'You do not own this section.');
        }
    }
}
