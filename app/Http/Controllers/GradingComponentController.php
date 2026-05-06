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
        $components = $section->gradingComponents()->get();
        $totalWeight = $components->sum('weight_percentage');

        return Inertia::render('class-record/Components', [
            'section' => $section,
            'components' => $components,
            'totalWeight' => $totalWeight,
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'weight_percentage' => 'required|numeric|min:0.01|max:100',
            'max_score' => 'required|numeric|min:1|max:100000',
        ]);

        $current = $section->gradingComponents()->sum('weight_percentage');
        if ($current + $validated['weight_percentage'] > 100.01) {
            return back()->withErrors(['weight_percentage' => 'Total weight cannot exceed 100%. Currently used: '.$current.'%.']);
        }

        $order = $section->gradingComponents()->max('order') + 1;

        $section->gradingComponents()->create([...$validated, 'order' => $order]);

        return back()->with('success', 'Component added.');
    }

    public function update(Request $request, GradingComponent $component): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'weight_percentage' => 'required|numeric|min:0.01|max:100',
            'max_score' => 'required|numeric|min:1|max:100000',
        ]);

        $others = $component->section->gradingComponents()
            ->where('id', '!=', $component->id)
            ->sum('weight_percentage');

        if ($others + $validated['weight_percentage'] > 100.01) {
            return back()->withErrors(['weight_percentage' => 'Total weight cannot exceed 100%. Other components use: '.$others.'%.']);
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
