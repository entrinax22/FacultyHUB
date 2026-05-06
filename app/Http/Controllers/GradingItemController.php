<?php

namespace App\Http\Controllers;

use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingItemController extends Controller
{
    public function index(Section $section): Response
    {
        $section->load(['subject', 'semester']);
        $components = $section->gradingComponents()->orderBy('order')->get();

        $items = GradingItem::query()
            ->where('section_id', $section->id)
            ->orderBy('component_id')
            ->orderBy('order')
            ->get();

        return Inertia::render('class-record/Items', [
            'section' => $section,
            'components' => $components,
            'items' => $items,
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'component_id' => 'required|exists:grading_components,id',
            'name' => 'required|string|max:100',
            'max_score' => 'required|numeric|min:1|max:100000',
        ]);

        $component = GradingComponent::query()
            ->where('section_id', $section->id)
            ->findOrFail($validated['component_id']);

        $order = GradingItem::query()
            ->where('section_id', $section->id)
            ->where('component_id', $component->id)
            ->max('order');
        $order = $order ? $order + 1 : 1;

        GradingItem::query()->create([
            'section_id' => $section->id,
            'component_id' => $component->id,
            'name' => $validated['name'],
            'max_score' => $validated['max_score'],
            'order' => $order,
            'is_enabled' => true,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, GradingItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'max_score' => 'required|numeric|min:1|max:100000',
        ]);

        $item->update($validated);

        return back()->with('success', 'Item updated.');
    }

    public function destroy(GradingItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    public function toggle(GradingItem $item): RedirectResponse
    {
        $item->update(['is_enabled' => ! $item->is_enabled]);

        return back()->with('success', $item->is_enabled ? 'Item enabled.' : 'Item disabled.');
    }
}

