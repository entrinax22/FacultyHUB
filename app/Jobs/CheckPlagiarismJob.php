<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\PlagiarismReport;
use App\Services\AIGraderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckPlagiarismJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly Assignment $assignment) {}

    public function handle(AIGraderService $ai): void
    {
        $submissions = $this->assignment->submissions()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->with('student')
            ->get();

        if ($submissions->count() < 2) {
            return;
        }

        $payload = $submissions->map(fn ($s) => [
            'student_id' => $s->student_id,
            'content' => $s->content,
        ])->toArray();

        $results = $ai->checkPlagiarism($payload, $this->assignment->title);

        if (empty($results) || isset($results['error'])) {
            return;
        }

        PlagiarismReport::where('assignment_id', $this->assignment->id)->delete();

        $flagThreshold = 70;

        foreach ($results as $pair) {
            if (! isset($pair['student_a'], $pair['student_b'], $pair['similarity'])) {
                continue;
            }

            PlagiarismReport::create([
                'assignment_id' => $this->assignment->id,
                'student_a_id' => $pair['student_a'],
                'student_b_id' => $pair['student_b'],
                'similarity_score' => $pair['similarity'],
                'flagged' => $pair['similarity'] >= $flagThreshold,
                'explanation' => $pair['explanation'] ?? null,
            ]);
        }
    }
}
