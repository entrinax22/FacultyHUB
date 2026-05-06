<?php

namespace App\Jobs;

use App\Models\AiFeedback;
use App\Models\Submission;
use App\Services\AIGraderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GradeEssayJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly Submission $submission) {}

    public function handle(AIGraderService $ai): void
    {
        $assignment = $this->submission->assignment;

        $result = $ai->gradeEssay(
            $assignment->title,
            $assignment->instructions,
            $assignment->rubric ?? 'Grade based on content quality, clarity, and relevance.',
            $this->submission->content ?? '',
            $assignment->max_score
        );

        if (isset($result['error'])) {
            $this->submission->update(['status' => 'pending']);

            return;
        }

        AiFeedback::updateOrCreate(
            ['submission_id' => $this->submission->id],
            [
                'score' => $result['score'] ?? 0,
                'feedback_json' => $result,
                'generated_at' => now(),
                'model_used' => config('services.anthropic.model'),
            ]
        );

        $this->submission->update(['status' => 'graded']);
    }
}
