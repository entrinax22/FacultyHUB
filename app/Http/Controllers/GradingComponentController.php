<?php

namespace App\Http\Controllers;

use App\Models\GradingComponent;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingComponentController extends Controller
{
    public function index(Section $section): Response
    {
        $section->load(['subject', 'semester']);
        $components = $section->gradingComponents()->orderBy('order')->get();

        return Inertia::render('class-record/Components', [
            'section'        => $section,
            'components'     => $components,
            'midtermWeight'  => $components->where('period', 'midterm')->sum('weight_percentage'),
            'finalsWeight'   => $components->where('period', 'finals')->sum('weight_percentage'),
            'generalWeight'  => $components->whereNull('period')->sum('weight_percentage'),
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'weight_percentage' => 'required|numeric|min:0.01|max:100',
            'max_score'        => 'required|numeric|min:1|max:100000',
            'period'           => 'nullable|in:midterm,finals',
        ]);

        $period  = $validated['period'] ?? null;
        $current = $section->gradingComponents()
            ->where('period', $period)
            ->sum('weight_percentage');

        if ($current + $validated['weight_percentage'] > 100.01) {
            $group = $period ? ucfirst($period) : 'General';
            return back()->withErrors(['weight_percentage' =>
                "{$group} weights cannot exceed 100%. Currently used: {$current}%."]);
        }

        $order = $section->gradingComponents()->max('order') + 1;
        $section->gradingComponents()->create([...$validated, 'order' => $order]);

        return back()->with('success', 'Component added.');
    }

    public function update(Request $request, GradingComponent $component): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'weight_percentage' => 'required|numeric|min:0.01|max:100',
            'max_score'        => 'required|numeric|min:1|max:100000',
            'period'           => 'nullable|in:midterm,finals',
        ]);

        $period = $validated['period'] ?? null;
        $others = $component->section->gradingComponents()
            ->where('id', '!=', $component->id)
            ->where('period', $period)
            ->sum('weight_percentage');

        if ($others + $validated['weight_percentage'] > 100.01) {
            $group = $period ? ucfirst($period) : 'General';
            return back()->withErrors(['weight_percentage' =>
                "{$group} weights cannot exceed 100%. Other components use: {$others}%."]);
        }

        $component->update($validated);

        return back()->with('success', 'Component updated.');
    }

    public function destroy(GradingComponent $component): RedirectResponse
    {
        if ($component->is_locked) {
            return back()->withErrors(['error' => 'This component is locked and cannot be deleted.']);
        }

        $component->delete();

        return back()->with('success', 'Component removed.');
    }

    public function toggleLock(GradingComponent $component): RedirectResponse
    {
        $component->update(['is_locked' => ! $component->is_locked]);

        return back()->with('success', $component->is_locked ? 'Component locked.' : 'Component unlocked.');
    }
}
