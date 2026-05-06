<?php

namespace App\Http\Controllers;

use App\Jobs\CheckPlagiarismJob;
use App\Models\Assignment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlagiarismController extends Controller
{
    public function show(Assignment $assignment): Response
    {
        $reports = $assignment->plagiarismReports()
            ->with(['studentA', 'studentB'])
            ->orderByDesc('similarity_score')
            ->get();

        return Inertia::render('assignments/PlagiarismReport', [
            'assignment' => $assignment->load('section.subject'),
            'reports' => $reports,
        ]);
    }

    public function run(Assignment $assignment): RedirectResponse
    {
        $count = $assignment->submissions()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->count();

        if ($count < 2) {
            return back()->withErrors(['run' => 'Need at least 2 text submissions to check for plagiarism.']);
        }

        CheckPlagiarismJob::dispatch($assignment);

        return back()->with('success', 'Plagiarism check queued. Results will appear shortly.');
    }
}
