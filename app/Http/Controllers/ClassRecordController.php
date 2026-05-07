<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\GradingItemScore;
use App\Models\Section;
use App\Models\TransmutationScale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassRecordController extends Controller
{
    public function show(Section $section): Response
    {
        $section->load(['subject', 'semester', 'faculty']);

        $components = $section->gradingComponents()->orderBy('order')->get();

        $items = GradingItem::query()
            ->where('section_id', $section->id)
            ->where('is_enabled', true)
            ->orderBy('component_id')
            ->orderBy('order')
            ->get();

        $enrollments = Enrollment::where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.last_name');

        $studentIds = $enrollments->pluck('student.id');

        // Grading item scores
        $itemIds    = $items->pluck('id');
        $itemScores = $itemIds->isEmpty()
            ? collect()
            : GradingItemScore::query()
                ->where('section_id', $section->id)
                ->whereIn('grading_item_id', $itemIds)
                ->get()
                ->groupBy(fn ($s) => "{$s->student_id}_{$s->grading_item_id}");

        // Assignments + their grades
        $assignments = Assignment::where('section_id', $section->id)
            ->where('is_published', true)
            ->orderBy('created_at')
            ->get(['id', 'title', 'max_score', 'type', 'period', 'category', 'component_id']);

        $assignmentGrades = $assignments->isEmpty() || $studentIds->isEmpty()
            ? collect()
            : Grade::where('section_id', $section->id)
                ->whereNotNull('assignment_id')
                ->whereIn('student_id', $studentIds)
                ->get()
                ->groupBy(fn ($g) => "{$g->student_id}_{$g->assignment_id}");

        $hasCustomScale   = TransmutationScale::where('section_id', $section->id)->exists();
        $itemsByComponent = $items->groupBy('component_id');

        // Assignments linked to a component already appear as grading items — exclude from the standalone column
        $standaloneAssignments = $assignments->whereNull('component_id')->values();

        // Fix: pass $scores as a parameter so the closure uses the correct per-student array
        $computePeriodGrade = function ($periodComponents, array $scores) use ($itemsByComponent) {
            $wSum   = 0;
            $wTotal = 0;
            foreach ($periodComponents as $comp) {
                $compItems = $itemsByComponent->get($comp->id, collect());
                if ($compItems->isEmpty()) continue;
                $raw = 0; $max = 0; $hasScore = false;
                foreach ($compItems as $item) {
                    $max += $item->max_score;
                    $val  = $scores[$item->id] ?? null;
                    if ($val !== null) { $hasScore = true; $raw += $val; }
                }
                if (! $hasScore || $max <= 0) continue;
                $wSum   += (($raw / $max) * 100 / 100) * $comp->weight_percentage;
                $wTotal += $comp->weight_percentage;
            }
            return $wTotal > 0 ? round(($wSum / $wTotal) * 100, 2) : null;
        };

        $midtermComponents = $components->where('period', 'midterm');
        $finalsComponents  = $components->where('period', 'finals');

        $rows = $enrollments->map(function ($enrollment) use (
            $components, $itemsByComponent, $items, $itemScores,
            $standaloneAssignments, $assignmentGrades, $section,
            $midtermComponents, $finalsComponents, $computePeriodGrade
        ) {
            $student = $enrollment->student;
            $scores  = [];

            foreach ($items as $item) {
                $key         = "{$student->id}_{$item->id}";
                $manualScore = $itemScores->get($key)?->first()?->score;

                if ($manualScore !== null) {
                    $scores[$item->id] = $manualScore;
                } elseif ($item->assignment_id) {
                    $aKey  = "{$student->id}_{$item->assignment_id}";
                    $grade = $assignmentGrades->get($aKey)?->first();
                    $scores[$item->id] = $grade?->raw_score;
                } else {
                    $scores[$item->id] = null;
                }
            }

            // Pass $scores explicitly so each student gets their own values
            $midtermGrade = $midtermComponents->isNotEmpty()
                ? $computePeriodGrade($midtermComponents, $scores)
                : null;

            $finalsGrade = $finalsComponents->isNotEmpty()
                ? $computePeriodGrade($finalsComponents, $scores)
                : null;

            if ($midtermGrade !== null && $finalsGrade !== null) {
                $totalGrade = round(($midtermGrade + $finalsGrade) / 2, 2);
            } elseif ($midtermGrade !== null || $finalsGrade !== null) {
                $totalGrade = $midtermGrade ?? $finalsGrade;
            } else {
                // No period-tagged components — fall back to overall weighted sum
                $wSum = 0; $wTotal = 0;
                foreach ($components as $comp) {
                    $compItems = $itemsByComponent->get($comp->id, collect());
                    if ($compItems->isEmpty()) continue;
                    $raw = 0; $max = 0; $hasScore = false;
                    foreach ($compItems as $item) {
                        $max += $item->max_score;
                        $val  = $scores[$item->id] ?? null;
                        if ($val !== null) { $hasScore = true; $raw += $val; }
                    }
                    if (! $hasScore || $max <= 0) continue;
                    $wSum   += (($raw / $max) * 100 / 100) * $comp->weight_percentage;
                    $wTotal += $comp->weight_percentage;
                }
                $totalGrade = $wTotal > 0 ? round(($wSum / $wTotal) * 100, 2) : null;
            }

            $finalGrade = $totalGrade !== null
                ? TransmutationScale::transmute($totalGrade, $section->id)
                : null;

            // Only show standalone assignments (linked ones are already in component columns)
            $studentAssignmentGrades = [];
            foreach ($standaloneAssignments as $a) {
                $key   = "{$student->id}_{$a->id}";
                $grade = $assignmentGrades->get($key)?->first();
                $studentAssignmentGrades[$a->id] = $grade ? [
                    'score'    => $grade->raw_score,
                    'max'      => $grade->max_score,
                    'pct'      => $grade->max_score > 0
                        ? round(($grade->raw_score / $grade->max_score) * 100, 1) : 0,
                    'released' => $grade->is_released,
                ] : null;
            }

            return [
                'student'              => $student,
                'enrollment_id'        => $enrollment->id,
                'scores'               => $scores,
                'assignment_grades'    => $studentAssignmentGrades,
                'midterm_grade'        => $midtermGrade,
                'finals_grade'         => $finalsGrade,
                'total_grade'          => $totalGrade,
                'final_grade'          => $finalGrade,
                'midterm_final_grade'  => $midtermGrade !== null
                    ? TransmutationScale::transmute($midtermGrade, $section->id)
                    : null,
                'finals_final_grade'   => $finalsGrade !== null
                    ? TransmutationScale::transmute($finalsGrade, $section->id)
                    : null,
            ];
        })->values();

        return Inertia::render('class-record/Index', [
            'section'       => $section,
            'components'    => $components,
            'items'         => $items,
            'assignments'   => $standaloneAssignments,
            'midtermWeight' => $components->where('period', 'midterm')->sum('weight_percentage'),
            'finalsWeight'  => $components->where('period', 'finals')->sum('weight_percentage'),
            'generalWeight' => $components->whereNull('period')->sum('weight_percentage'),
            'rows'          => $rows,
            'hasCustomScale' => $hasCustomScale,
            'defaultScale'  => TransmutationScale::defaultScale(),
        ]);
    }

    public function updateGrade(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'section_id' => 'required|exists:sections,id',
            'component_id' => 'required|exists:grading_components,id',
            'score' => 'nullable|numeric|min:0',
        ]);

        $component = GradingComponent::query()->findOrFail($validated['component_id']);
        $maxScore = $component->max_score ?? 100;

        if ($validated['score'] !== null && $validated['score'] > $maxScore) {
            return back()->withErrors(['score' => "Score cannot exceed {$maxScore} for {$component->name}."]);
        }

        if ($validated['score'] === null) {
            Grade::where('student_id', $validated['student_id'])
                ->where('section_id', $validated['section_id'])
                ->where('component_id', $validated['component_id'])
                ->whereNull('submission_id')
                ->delete();
        } else {
            Grade::updateOrCreate(
                [
                    'student_id' => $validated['student_id'],
                    'section_id' => $validated['section_id'],
                    'component_id' => $validated['component_id'],
                    'submission_id' => null,
                ],
                [
                    'assignment_id' => null,
                    'raw_score' => $validated['score'],
                    'max_score' => $maxScore,
                    'is_released' => false,
                ]
            );
        }

        return back()->with('success', 'Grade saved.');
    }

    public function updateItemScore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'section_id' => 'required|exists:sections,id',
            'item_id' => 'required|exists:grading_items,id',
            'score' => 'nullable|numeric|min:0',
        ]);

        $item = GradingItem::query()
            ->where('section_id', $validated['section_id'])
            ->findOrFail($validated['item_id']);

        if ($validated['score'] !== null && $validated['score'] > $item->max_score) {
            return back()->withErrors(['score' => "Score cannot exceed {$item->max_score} for {$item->name}."]);
        }

        if ($validated['score'] === null) {
            GradingItemScore::query()
                ->where('section_id', $validated['section_id'])
                ->where('student_id', $validated['student_id'])
                ->where('grading_item_id', $item->id)
                ->delete();
        } else {
            GradingItemScore::query()->updateOrCreate(
                [
                    'section_id' => $validated['section_id'],
                    'student_id' => $validated['student_id'],
                    'grading_item_id' => $item->id,
                ],
                [
                    'score' => $validated['score'],
                    'is_released' => false,
                ],
            );
        }

        return back()->with('success', 'Score saved.');
    }

    public function releaseAll(Section $section): RedirectResponse
    {
        Grade::where('section_id', $section->id)
            ->whereNull('submission_id')
            ->whereNotNull('component_id')
            ->update(['is_released' => true]);

        GradingItemScore::query()
            ->where('section_id', $section->id)
            ->update(['is_released' => true]);

        return back()->with('success', 'All component grades released to students.');
    }
}
