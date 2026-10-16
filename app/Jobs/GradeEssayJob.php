<?php

namespace App\Jobs;

use App\Models\AiFeedback;
use App\Models\Submission;
use App\Services\AIGraderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GradeEssayJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

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
            throw new \RuntimeException('AI grading failed: ' . $result['error']);
        }

        $result = $ai->validateGradeResponse(
            $result,
            $assignment->max_score,
            'essay'
        );

        AiFeedback::updateOrCreate(
            ['submission_id' => $this->submission->id],
            [
                'score'         => $result['score'] ?? 0,
                'feedback_json' => $result,
                'generated_at'  => now(),
                'model_used'    => $ai->modelUsed(),
            ]
        );

        $this->submission->update(['status' => 'graded']);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('AI essay grading failed.', [
            'submission_id' => $this->submission->id,
            'failure_type' => $e::class,
        ]);
        $this->submission->update(['status' => 'pending']);
    }
}
