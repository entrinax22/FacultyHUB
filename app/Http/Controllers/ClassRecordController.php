<?php

namespace App\Http\Controllers;

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

        $components = $section->gradingComponents()->get();
        $totalWeight = $components->sum('weight_percentage');

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

        $itemIds = $items->pluck('id');
        $itemScores = $itemIds->isEmpty()
            ? collect()
            : GradingItemScore::query()
                ->where('section_id', $section->id)
                ->whereIn('grading_item_id', $itemIds)
                ->get()
                ->groupBy(fn ($s) => "{$s->student_id}_{$s->grading_item_id}");

        $hasCustomScale = TransmutationScale::where('section_id', $section->id)->exists();

        $itemsByComponent = $items->groupBy('component_id');

        $rows = $enrollments->map(function ($enrollment) use ($components, $itemsByComponent, $items, $itemScores, $section) {
            $student = $enrollment->student;
            $weightedSum = 0;
            $totalWeight = 0;
            $scores = [];

            foreach ($items as $item) {
                $key = "{$student->id}_{$item->id}";
                $score = $itemScores->get($key)?->first()?->score;
                $scores[$item->id] = $score;
            }

            foreach ($components as $comp) {
                $compItems = $itemsByComponent->get($comp->id, collect());
                if ($compItems->isEmpty()) {
                    continue;
                }

                $componentHasAnyScore = false;
                $componentRaw = 0;
                $componentMax = 0;

                foreach ($compItems as $item) {
                    $componentMax += $item->max_score;
                    $val = $scores[$item->id] ?? null;
                    if ($val !== null) {
                        $componentHasAnyScore = true;
                        $componentRaw += $val;
                    }
                }

                if (! $componentHasAnyScore || $componentMax <= 0) {
                    continue;
                }

                $componentPercent = ($componentRaw / $componentMax) * 100;
                $weightedSum += ($componentPercent / 100) * $comp->weight_percentage;
                $totalWeight += $comp->weight_percentage;
            }

            $finalGrade = $totalWeight > 0 ? round(($weightedSum / $totalWeight) * 100, 2) : null;
            $transmuted = $finalGrade !== null
                ? TransmutationScale::transmute($finalGrade, $section->id)
                : null;

            return [
                'student' => $student,
                'enrollment_id' => $enrollment->id,
                'scores' => $scores,
                'final_grade' => $finalGrade,
                'transmuted' => $transmuted,
            ];
        })->values();

        return Inertia::render('class-record/Index', [
            'section' => $section,
            'components' => $components,
            'items' => $items,
            'totalWeight' => $totalWeight,
            'rows' => $rows,
            'hasCustomScale' => $hasCustomScale,
            'defaultScale' => TransmutationScale::defaultScale(),
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
