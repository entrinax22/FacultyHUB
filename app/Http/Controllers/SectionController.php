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
    public function index(): Response
    {
        $sections = Section::with(['semester', 'subject', 'faculty'])
            ->withCount('enrollments')
            ->latest()
            ->get();

        return Inertia::render('sections/Index', [
            'sections' => $sections,
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

    public function show(Section $section): Response
    {
        $section->load(['semester', 'subject', 'faculty']);
        $enrollments = $section->enrollments()->with('student')->get();

        return Inertia::render('sections/Show', [
            'section' => $section,
            'enrollments' => $enrollments,
        ]);
    }

    public function edit(Section $section): Response
    {
        return Inertia::render('sections/Form', [
            'section' => $section,
            'semesters' => Semester::orderByDesc('is_active')->latest()->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
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

    public function destroy(Section $section): RedirectResponse
    {
        $section->delete();

        return redirect()->route('sections.index')->with('success', 'Section deleted.');
    }
}
