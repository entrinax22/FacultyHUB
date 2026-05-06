<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Module;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Section $section): Response
    {
        $section->load(['subject', 'semester']);
        $assignments = $section->assignments()
            ->withCount('submissions')
            ->latest()
            ->get();

        return Inertia::render('assignments/Index', [
            'section' => $section,
            'assignments' => $assignments,
        ]);
    }

    public function create(Section $section): Response
    {
        $section->load(['subject', 'semester']);

        return Inertia::render('assignments/Form', [
            'section' => $section,
            'modules' => $section->modules()->where('is_published', true)->get(['id', 'title']),
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $this->validateAssignment($request);
        $assignment = $section->assignments()->create($validated);

        if ($assignment->type === 'mcq') {
            $this->syncQuestions($assignment, $request->input('questions', []));
        }

        return redirect()
            ->route('sections.assignments.index', $section)
            ->with('success', 'Assignment created.');
    }

    public function show(Assignment $assignment): Response
    {
        $assignment->load(['section.subject', 'section.semester', 'questions.choices']);

        $submissions = $assignment->submissions()
            ->with(['student', 'grade', 'aiFeedback'])
            ->latest('submitted_at')
            ->get();

        return Inertia::render('assignments/Show', [
            'assignment' => $assignment,
            'submissions' => $submissions,
            'plagiarismRan' => $assignment->plagiarismReports()->exists(),
        ]);
    }

    public function edit(Assignment $assignment): Response
    {
        $assignment->load(['section.subject', 'section.semester', 'questions.choices']);

        return Inertia::render('assignments/Form', [
            'assignment' => $assignment,
            'section' => $assignment->section,
            'modules' => $assignment->section->modules()->where('is_published', true)->get(['id', 'title']),
        ]);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $this->validateAssignment($request);
        $assignment->update($validated);

        if ($assignment->type === 'mcq') {
            $this->syncQuestions($assignment, $request->input('questions', []));
        }

        return redirect()
            ->route('sections.assignments.index', $assignment->section_id)
            ->with('success', 'Assignment updated.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $sectionId = $assignment->section_id;
        $assignment->delete();

        return redirect()
            ->route('sections.assignments.index', $sectionId)
            ->with('success', 'Assignment deleted.');
    }

    public function togglePublish(Assignment $assignment): RedirectResponse
    {
        $assignment->update(['is_published' => ! $assignment->is_published]);

        return back()->with('success', $assignment->is_published ? 'Assignment published.' : 'Assignment set to draft.');
    }

    private function validateAssignment(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'required|string',
            'type' => 'required|in:essay,mcq,code',
            'due_date' => 'nullable|date',
            'max_score' => 'required|numeric|min:1',
            'passing_score' => 'nullable|numeric|min:0',
            'is_published' => 'boolean',
            'rubric' => 'nullable|string',
            'language' => 'nullable|string|max:30',
            'answer_release_at' => 'nullable|date',
            'module_id' => 'nullable|exists:modules,id',
        ]);
    }

    private function syncQuestions(Assignment $assignment, array $questions): void
    {
        $existingIds = [];

        foreach ($questions as $order => $qData) {
            $question = isset($qData['id'])
                ? $assignment->questions()->find($qData['id'])
                : null;

            if ($question) {
                $question->update(['question' => $qData['question'], 'order' => $order, 'points' => $qData['points'] ?? 1]);
            } else {
                $question = $assignment->questions()->create([
                    'question' => $qData['question'],
                    'order' => $order,
                    'points' => $qData['points'] ?? 1,
                ]);
            }

            $existingIds[] = $question->id;
            $this->syncChoices($question, $qData['choices'] ?? []);
        }

        $assignment->questions()->whereNotIn('id', $existingIds)->delete();
    }

    private function syncChoices($question, array $choices): void
    {
        $existingIds = [];

        foreach ($choices as $order => $cData) {
            $choice = isset($cData['id'])
                ? $question->choices()->find($cData['id'])
                : null;

            if ($choice) {
                $choice->update(['choice_text' => $cData['choice_text'], 'is_correct' => $cData['is_correct'] ?? false, 'order' => $order]);
            } else {
                $choice = $question->choices()->create([
                    'choice_text' => $cData['choice_text'],
                    'is_correct' => $cData['is_correct'] ?? false,
                    'order' => $order,
                ]);
            }

            $existingIds[] = $choice->id;
        }

        $question->choices()->whereNotIn('id', $existingIds)->delete();
    }
}
