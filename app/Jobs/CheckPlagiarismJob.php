<?php

namespace App\Jobs;

use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\PlagiarismReport;
use App\Services\AIGraderService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CheckPlagiarismJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly Assignment $assignment,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->assignment->id;
    }

    public function handle(AIGraderService $ai): void
    {
        if ($this->hasPairwiseProcessingState()) {
            return;
        }

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

        DB::transaction(function () use ($results) {
            $assignment = Assignment::query()
                ->lockForUpdate()
                ->findOrFail($this->assignment->id);

            if ($this->hasPairwiseProcessingState($assignment)) {
                return;
            }

            PlagiarismReport::where('assignment_id', $assignment->id)->delete();

            foreach ($results as $pair) {
                if (! isset($pair['student_a'], $pair['student_b'], $pair['similarity'])) {
                    continue;
                }

                PlagiarismReport::create([
                    'assignment_id' => $assignment->id,
                    'student_a_id' => $pair['student_a'],
                    'student_b_id' => $pair['student_b'],
                    'similarity_score' => $pair['similarity'],
                    'flagged' => $pair['similarity'] >= 70,
                    'explanation' => $pair['explanation'] ?? null,
                ]);
            }
        });
    }

    private function hasPairwiseProcessingState(?Assignment $assignment = null): bool
    {
        $assignment ??= $this->assignment;

        return $assignment->plagiarism_published_run_id !== null
            || AiProcessingRun::query()
                ->where('resource_type', Assignment::class)
                ->where('resource_id', $assignment->id)
                ->where('processing_type', AiProcessingRun::TYPE_PLAGIARISM)
                ->exists();
    }
}
