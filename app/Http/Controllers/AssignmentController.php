<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\Section;
use App\Services\AIGraderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Smalot\PdfParser\Parser;
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
            'section'    => $section,
            'modules'    => $section->modules()->where('is_published', true)->get(['id', 'title']),
            'components' => $section->gradingComponents()->orderBy('order')->get(['id', 'name', 'period', 'weight_percentage']),
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {  
        if (!$section->gradingComponents()->exists()) {
            return redirect()
                ->route('sections.assignments.index', $section)
                ->with(
                    'warning',
                    'Please set up your grading components before creating an assignment.'
                );
        }

        $validated = $this->validateAssignment($request);
        $assignment = $section->assignments()->create($validated);

        if ($assignment->type === 'mcq') {
            $this->syncQuestions($assignment, $request->input('questions', []));
        }

        $this->syncGradingItem($assignment);

        return redirect()
            ->route('sections.assignments.index', $section)
            ->with('success', 'Assignment created.');
    }

    public function importPdf(Request $request, Section $section, Parser $parser, AIGraderService $ai): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        try {
            $document = $parser->parseFile($validated['document']->getRealPath());
            $text = trim($document->getText());

            if ($text === '') {
                return response()->json(['message' => 'The PDF does not contain readable text.'], 422);
            }

            if (mb_strlen($text) > 120000) {
                $text = mb_substr($text, 0, 120000);
            }

            $draft = $ai->createAssignmentDraft($text);

            if (isset($draft['error'])) {
                return response()->json(['message' => $draft['error']], 422);
            }

            return response()->json([
                'title' => (string) ($draft['title'] ?? ''),
                'type' => in_array($draft['type'] ?? null, ['essay', 'mcq', 'code'], true) ? $draft['type'] : 'essay',
                'category' => in_array($draft['category'] ?? null, ['quiz', 'exam', 'activity', 'project'], true) ? $draft['category'] : null,
                'language' => in_array($draft['language'] ?? null, ['python', 'javascript', 'java', 'cpp', 'csharp', 'php', 'ruby', 'go'], true) ? $draft['language'] : null,
                'instructions' => (string) ($draft['instructions'] ?? ''),
                'rubric' => (string) ($draft['rubric'] ?? ''),
                'questions' => collect($draft['questions'] ?? [])
                    ->filter(fn ($question) => filled($question['question'] ?? null))
                    ->map(fn ($question) => [
                        'question' => (string) $question['question'],
                        'points' => max(0.5, (float) ($question['points'] ?? 1)),
                        'choices' => collect($question['choices'] ?? [])
                            ->filter(fn ($choice) => filled($choice['choice_text'] ?? null))
                            ->map(fn ($choice) => [
                                'choice_text' => (string) $choice['choice_text'],
                                'is_correct' => (bool) ($choice['is_correct'] ?? false),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The PDF could not be processed. Please check the file and try again.'], 422);
        }
    }

    public function show(Assignment $assignment): Response
    {
        $assignment->load(['section.subject', 'section.semester', 'questions.choices']);

        $submissions = $assignment->submissions()
            ->withCount([
                'proctoringEvents as proctoring_events_count',
                'proctoringEvents as proctoring_alerts_count' => fn ($query) => $query->whereIn('event_type', [
                    'tab_hidden',
                    'window_resized',
                    'fullscreen_exited',
                    'copy_detected',
                    'paste_detected',
                    'cut_detected',
                    'context_menu_used',
                    'print_screen_suspected',
                    'camera_permission_denied',
                ]),
            ])
            ->with(['student', 'grade', 'aiFeedback', 'proctoringEvents' => fn ($query) => $query->latest()->limit(10)])
            ->latest('submitted_at')
            ->get();

        return Inertia::render('assignments/Show', [
            'assignment'    => $assignment,
            'submissions'   => $submissions,
            'plagiarismRan' => $assignment->plagiarismReports()->exists(),
        ]);
    }

    public function edit(Assignment $assignment): Response
    {
        $assignment->load(['section.subject', 'section.semester', 'questions.choices']);

        return Inertia::render('assignments/Form', [
            'assignment' => $assignment,
            'section'    => $assignment->section,
            'modules'    => $assignment->section->modules()->where('is_published', true)->get(['id', 'title']),
            'components' => $assignment->section->gradingComponents()->orderBy('order')->get(['id', 'name', 'period', 'weight_percentage']),
        ]);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $this->validateAssignment($request);
        $assignment->update($validated);

        if ($assignment->type === 'mcq') {
            $this->syncQuestions($assignment, $request->input('questions', []));
        }

        $this->syncGradingItem($assignment);

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
        $request->merge([
            'proctoring_enabled' => $request->boolean('proctoring_enabled'),
        ]);

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'instructions'      => 'required|string',
            'type'              => 'required|in:essay,mcq,code',
            'period'            => 'nullable|in:midterm,finals',
            'category'          => 'nullable|in:quiz,exam,activity,project',
            'component_id'      => 'nullable|exists:grading_components,id',
            'due_date'          => 'nullable|date',
            'max_score'         => 'required|numeric|min:1',
            'passing_score'     => 'nullable|numeric|min:0',
            'is_published'      => 'boolean',
            'rubric'            => 'nullable|string',
            'language'          => 'nullable|string|max:30',
            'answer_release_at' => 'nullable|date',
            'module_id'         => 'nullable|exists:modules,id',
            'duration_minutes'  => 'nullable|integer|min:1|max:600',
            'proctoring_enabled' => ['required', 'boolean'],
        ]);

        // Always persist the checkbox value, including false when monitoring is disabled.
        $validated['proctoring_enabled'] = $request->boolean('proctoring_enabled');

        $isTimedAssignment = ($validated['category'] ?? null) === 'exam'
            || (($validated['type'] ?? null) === 'mcq' && ! empty($validated['proctoring_enabled']));

        if ($isTimedAssignment && empty($validated['duration_minutes'])) {
            validator()->make($validated, ['duration_minutes' => 'required|integer|min:1|max:600'])->validate();
        }

        return $validated;
    }

    private function syncGradingItem(Assignment $assignment): void
    {
        // Remove existing linked item if component was cleared
        if (! $assignment->component_id) {
            GradingItem::where('assignment_id', $assignment->id)->delete();
            return;
        }

        $existing = GradingItem::where('assignment_id', $assignment->id)->first();

        if ($existing) {
            $existing->update([
                'component_id' => $assignment->component_id,
                'name'         => $assignment->title,
                'max_score'    => $assignment->max_score,
            ]);
        } else {
            $order = GradingItem::where('section_id', $assignment->section_id)
                ->where('component_id', $assignment->component_id)
                ->max('order') + 1;

            GradingItem::create([
                'section_id'    => $assignment->section_id,
                'component_id'  => $assignment->component_id,
                'assignment_id' => $assignment->id,
                'name'          => $assignment->title,
                'max_score'     => $assignment->max_score,
                'order'         => $order ?: 1,
                'is_enabled'    => true,
            ]);
        }
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
                    'order'    => $order,
                    'points'   => $qData['points'] ?? 1,
                ]);
            }

            $existingIds[] = $question->id;
            $this->syncChoices($question, $qData['choices'] ?? []);
        }

        $assignment->questions()->whereNotIn('id', $existingIds)->delete();
    }

    private function syncChoices(\App\Models\AssignmentQuestion $question, array $choices): void
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
                    'is_correct'  => $cData['is_correct'] ?? false,
                    'order'       => $order,
                ]);
            }

            $existingIds[] = $choice->id;
        }

        $question->choices()->whereNotIn('id', $existingIds)->delete();
    }
}
