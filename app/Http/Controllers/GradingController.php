<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingController extends Controller
{
    public function show(Submission $submission): Response
    {
        $submission->load([
            'assignment.section.subject',
            'assignment.questions.choices',
            'student',
            'grade',
            'aiFeedback',
        ]);

        return Inertia::render('assignments/Grading', [
            'submission' => $submission,
        ]);
    }

    public function approve(Request $request, Submission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'raw_score' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ]);

        Grade::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'student_id' => $submission->student_id,
                'section_id' => $submission->assignment->section_id,
                'assignment_id' => $submission->assignment_id,
                'raw_score' => $validated['raw_score'],
                'max_score' => $submission->assignment->max_score,
                'remarks' => $validated['remarks'] ?? null,
                'is_released' => false,
            ]
        );

        $submission->update(['status' => 'approved']);

        return redirect()
            ->route('assignments.show', $submission->assignment_id)
            ->with('success', 'Grade approved for '.$submission->student->full_name.'.');
    }

    public function releaseGrades(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignment_id' => 'required|exists:assignments,id',
        ]);

        Grade::where('assignment_id', $validated['assignment_id'])
            ->update(['is_released' => true]);

        return back()->with('success', 'All grades released to students.');
    }
}
