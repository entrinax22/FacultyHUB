<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\TransmutationScale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransmutationController extends Controller
{
    public function index(Section $section): Response
    {
        $section->load(['subject', 'semester']);
        $scale = TransmutationScale::where('section_id', $section->id)
            ->orderByDesc('min_score')
            ->get();

        return Inertia::render('class-record/Transmutation', [
            'section' => $section,
            'scale' => $scale,
            'defaultScale' => TransmutationScale::defaultScale(),
        ]);
    }

    public function useDefault(Section $section): RedirectResponse
    {
        TransmutationScale::where('section_id', $section->id)->delete();

        foreach (TransmutationScale::defaultScale() as $row) {
            TransmutationScale::create([
                'section_id' => $section->id,
                'min_score' => $row['min'],
                'max_score' => $row['max'],
                'grade' => $row['grade'],
                'description' => $row['description'],
            ]);
        }

        return back()->with('success', 'Default Philippine grading scale applied.');
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.min_score' => 'required|numeric|min:0|max:100',
            'rows.*.max_score' => 'required|numeric|min:0|max:100|gte:rows.*.min_score',
            'rows.*.grade' => 'required|string|max:10',
            'rows.*.description' => 'nullable|string|max:50',
        ]);

        TransmutationScale::where('section_id', $section->id)->delete();

        foreach ($validated['rows'] as $row) {
            TransmutationScale::create([
                'section_id' => $section->id,
                'min_score' => $row['min_score'],
                'max_score' => $row['max_score'],
                'grade' => $row['grade'],
                'description' => $row['description'] ?? null,
            ]);
        }

        return back()->with('success', 'Transmutation scale saved.');
    }

    public function reset(Section $section): RedirectResponse
    {
        TransmutationScale::where('section_id', $section->id)->delete();

        return back()->with('success', 'Custom scale removed. Default scale is now active.');
    }
}
