<?php

use App\Models\AiProcessingBudgetWindow;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\PlagiarismRunPair;
use App\Models\PlagiarismRunSubmission;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Services\AIGraderService;
use App\Services\AiProcessingRunService;
use Symfony\Component\HttpKernel\Exception\HttpException;

function createPlagiarismPreparationFixture(int $submissionCount = 0): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create([
        'name' => 'Second Semester',
        'school_year' => '2026-2027',
        'start_date' => '2027-01-01',
        'end_date' => '2027-05-31',
        'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'PLG301', 'name' => 'Plagiarism Foundations']);
    $section = Section::create([
        'name' => 'BSCS 3B',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'title' => 'Pairwise Processing',
        'instructions' => 'Submit a written response.',
        'type' => 'essay',
        'max_score' => 20,
        'is_published' => true,
    ]);

    $submissions = collect($submissionCount > 0 ? range(1, $submissionCount) : [])
        ->map(function (int $number) use ($assignment) {
            $student = Student::create([
                'student_no' => 'PLG-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Student',
                'last_name' => (string) $number,
                'email' => "plagiarism-{$number}@example.test",
                'course' => 'BSCS',
                'year_level' => 3,
            ]);

            return Submission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'content' => "Submission content {$number}",
            ]);
        });

    return compact('faculty', 'assignment', 'submissions');
}

test('prepares the exact number of pairs for eligible submissions', function (int $submissionCount, int $expectedPairs) {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture($submissionCount);

    $run = app(AiProcessingRunService::class)->preparePlagiarismRun($faculty, $assignment);

    expect($run->plagiarismSubmissions()->count())->toBe($submissionCount)
        ->and($run->plagiarismPairs()->count())->toBe($expectedPairs)
        ->and($run->total_work_units)->toBe($expectedPairs)
        ->and($run->preparation_status)->toBe(AiProcessingRun::PREPARATION_PREPARED);
})->with([
    'zero submissions' => [0, 0],
    'one submission' => [1, 0],
    'two submissions' => [2, 1],
    'three submissions' => [3, 3],
    'ten submissions' => [10, 45],
]);

test('creates canonical deterministic pairs and returns the same active run', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(3);
    $service = app(AiProcessingRunService::class);

    $run = $service->preparePlagiarismRun($faculty, $assignment);
    $sameRun = $service->preparePlagiarismRun($faculty, $assignment);
    $pairs = $run->plagiarismPairs()
        ->with(['snapshotA', 'snapshotB'])
        ->orderBy('snapshot_a_id')
        ->orderBy('snapshot_b_id')
        ->get()
        ->map(fn ($pair) => [$pair->snapshotA->ordinal, $pair->snapshotB->ordinal])
        ->all();

    expect($sameRun->id)->toBe($run->id)
        ->and($pairs)->toBe([[1, 2], [1, 3], [2, 3]])
        ->and($run->plagiarismPairs()->count())->toBe(3);
});

test('preserves immutable snapshot content when a source submission changes', function () {
    ['faculty' => $faculty, 'assignment' => $assignment, 'submissions' => $submissions] = createPlagiarismPreparationFixture(2);
    $run = app(AiProcessingRunService::class)->preparePlagiarismRun($faculty, $assignment);
    $source = $submissions->first();
    $snapshot = $run->plagiarismSubmissions()->where('submission_id', $source->id)->firstOrFail();

    $source->update(['content' => 'Edited after snapshot']);

    expect($snapshot->fresh()->content)->toBe('Submission content 1')
        ->and($snapshot->content_hash)->toBe(hash('sha256', 'Submission content 1'));
});

test('resumes pair generation from existing snapshots without duplicate pairs', function () {
    ['faculty' => $faculty, 'assignment' => $assignment, 'submissions' => $submissions] = createPlagiarismPreparationFixture(3);
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_PLAGIARISM, 0);

    foreach ($submissions as $index => $submission) {
        PlagiarismRunSubmission::create([
            'ai_processing_run_id' => $run->id,
            'submission_id' => $submission->id,
            'student_id' => $submission->student_id,
            'ordinal' => $index + 1,
            'content' => $submission->content,
            'content_hash' => hash('sha256', $submission->content),
        ]);
    }

    $snapshots = $run->plagiarismSubmissions()->orderBy('ordinal')->get();
    PlagiarismRunPair::create([
        'ai_processing_run_id' => $run->id,
        'snapshot_a_id' => $snapshots[0]->id,
        'snapshot_b_id' => $snapshots[1]->id,
        'status' => PlagiarismRunPair::STATUS_PENDING,
    ]);

    $prepared = $service->preparePlagiarismRun($faculty, $assignment);

    expect($prepared->plagiarismPairs()->count())->toBe(3)
        ->and($prepared->preparation_status)->toBe(AiProcessingRun::PREPARATION_PREPARED);
});

test('excludes submissions created after an immutable snapshot is prepared', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->preparePlagiarismRun($faculty, $assignment);

    $student = Student::create([
        'student_no' => 'PLG-LATE',
        'first_name' => 'Late',
        'last_name' => 'Student',
        'email' => 'plagiarism-late@example.test',
        'course' => 'BSCS',
        'year_level' => 3,
    ]);
    Submission::create([
        'assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'content' => 'Submitted after snapshot',
    ]);

    expect($run->plagiarismSubmissions()->count())->toBe(2)
        ->and($run->plagiarismPairs()->count())->toBe(1);
});

test('rejects preparation for another faculty members assignment', function () {
    ['assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $otherFaculty = User::factory()->create(['role' => 'faculty']);

    expect(fn () => app(AiProcessingRunService::class)->preparePlagiarismRun($otherFaculty, $assignment))
        ->toThrow(HttpException::class);
});

test('successful bounded claims do not consume retries', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_GRADING, 5);

    foreach (range(1, 5) as $unit) {
        $lease = $service->claim($run, $faculty);
        $run = $service->recordProgress($run, $faculty, $lease, 1);
    }

    expect($run->status)->toBe(AiProcessingRun::STATUS_COMPLETED)
        ->and($run->retry_count)->toBe(0)
        ->and($run->completed_work_units)->toBe(5);
});

test('expired leases count as failures and reject stale lease commits', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_GRADING, 1);
    $staleLease = $service->claim($run, $faculty);
    $run->fresh()->update(['lease_expires_at' => now()->subSecond()]);

    $service->recoverExpiredRuns();
    $freshLease = $service->claim($run->fresh(), $faculty);

    expect($run->fresh()->retry_count)->toBe(1)
        ->and($freshLease)->toBeString()
        ->and(fn () => $service->recordProgress($run, $faculty, $staleLease, 1))
        ->toThrow(RuntimeException::class);
});

function plagiarismAiMock(array $response, int $estimatedTokens = 100): AIGraderService
{
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('modelUsed')->andReturn('test-model');
    $ai->shouldReceive('estimatePlagiarismPairInputTokens')->andReturn($estimatedTokens);
    $ai->shouldReceive('analyzePlagiarismPair')->andReturn($response);
    $ai->shouldReceive('validatePlagiarismPairResponse')->andReturnUsing(function (array $result) {
        if (! isset($result['similarity'], $result['flagged'], $result['explanation'])) {
            throw new InvalidArgumentException('Invalid response.');
        }

        if (! is_numeric($result['similarity']) || $result['similarity'] < 0 || $result['similarity'] > 100) {
            throw new InvalidArgumentException('Invalid similarity.');
        }

        if (! is_bool($result['flagged']) || $result['flagged'] !== ($result['similarity'] >= 70)) {
            throw new InvalidArgumentException('Invalid flag.');
        }

        if (! is_string($result['explanation']) || trim($result['explanation']) === '') {
            throw new InvalidArgumentException('Invalid explanation.');
        }

        return $result;
    });

    return $ai;
}

test('processes one staged pair without publishing historical reports', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->preparePlagiarismRun($faculty, $assignment);

    $processed = $service->processNextPlagiarismPair($run, $faculty, plagiarismAiMock([
        'similarity' => 72.5,
        'flagged' => true,
        'explanation' => 'Substantial matching structure.',
    ]));
    $pair = $run->plagiarismPairs()->firstOrFail()->fresh();

    expect($processed->status)->toBe(AiProcessingRun::STATUS_COMPLETED)
        ->and($pair->status)->toBe(PlagiarismRunPair::STATUS_COMPLETED)
        ->and($pair->similarity_score)->toBe(72.5)
        ->and($pair->flagged)->toBeTrue()
        ->and($assignment->plagiarismReports()->count())->toBe(0)
        ->and(AiProcessingBudgetWindow::query()->where('window_type', 'daily')->value('request_count'))->toBe(1);
});

test('rejects malformed pair results without recording completed work', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->preparePlagiarismRun($faculty, $assignment);

    $processed = $service->processNextPlagiarismPair($run, $faculty, plagiarismAiMock([
        'similarity' => 101,
        'flagged' => true,
        'explanation' => 'Invalid score.',
    ]));

    expect($processed->status)->toBe(AiProcessingRun::STATUS_FAILED)
        ->and($run->plagiarismPairs()->first()->status)->toBe(PlagiarismRunPair::STATUS_FAILED)
        ->and($processed->completed_work_units)->toBe(0);
});

test('validates pairwise similarity, flags, and explanations strictly', function () {
    $ai = app(AIGraderService::class);

    expect($ai->validatePlagiarismPairResponse([
        'similarity' => 70,
        'flagged' => true,
        'explanation' => 'Matching wording was detected.',
    ]))->toMatchArray(['similarity' => 70, 'flagged' => true])
        ->and(fn () => $ai->validatePlagiarismPairResponse([
            'similarity' => 70,
            'flagged' => false,
            'explanation' => 'Inconsistent flag.',
        ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $ai->validatePlagiarismPairResponse([
            'similarity' => 20,
            'flagged' => false,
        ]))->toThrow(InvalidArgumentException::class);
});

test('returns an oversized pair as a terminal failure without reserving provider budget', function () {
    config()->set('ai_processing.plagiarism_max_pair_content_bytes', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->preparePlagiarismRun($faculty, $assignment);
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('estimatePlagiarismPairInputTokens')->andThrow(new InvalidArgumentException('Too large.'));

    $processed = $service->processNextPlagiarismPair($run, $faculty, $ai);

    expect($processed->status)->toBe(AiProcessingRun::STATUS_FAILED)
        ->and(AiProcessingBudgetWindow::query()->count())->toBe(0);
});

test('pauses a pair without consuming an attempt when the application budget is exhausted', function () {
    config()->set('ai_processing.plagiarism_daily_request_budget', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->preparePlagiarismRun($faculty, $assignment);
    AiProcessingBudgetWindow::create([
        'provider' => 'gemini',
        'model' => 'test-model',
        'window_type' => 'daily',
        'window_starts_at' => now(config('ai_processing.plagiarism_budget_timezone'))->startOfDay()->utc(),
        'request_count' => 1,
        'estimated_input_tokens' => 100,
    ]);

    $processed = $service->processNextPlagiarismPair($run, $faculty, plagiarismAiMock([
        'similarity' => 0,
        'flagged' => false,
        'explanation' => 'Should not be requested.',
    ]));
    $pair = $run->plagiarismPairs()->first()->fresh();

    expect($processed->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($processed->error_code)->toBe('budget_exhausted')
        ->and($pair->status)->toBe(PlagiarismRunPair::STATUS_PENDING)
        ->and($pair->attempt_count)->toBe(0);
});

test('defers provider rate limits with a bounded pair retry', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->preparePlagiarismRun($faculty, $assignment);

    $processed = $service->processNextPlagiarismPair($run, $faculty, plagiarismAiMock([
        'error' => 'Rate limited.',
        'error_code' => 'provider_rate_limited',
    ]));
    $pair = $run->plagiarismPairs()->first()->fresh();

    expect($processed->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($pair->status)->toBe(PlagiarismRunPair::STATUS_PENDING)
        ->and($pair->attempt_count)->toBe(1)
        ->and($pair->next_eligible_at)->not->toBeNull();
});

test('rejects unauthorized pair processing', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismPreparationFixture(2);
    $run = app(AiProcessingRunService::class)->preparePlagiarismRun($faculty, $assignment);
    $otherFaculty = User::factory()->create(['role' => 'faculty']);

    expect(fn () => app(AiProcessingRunService::class)->processNextPlagiarismPair(
        $run,
        $otherFaculty,
        plagiarismAiMock(['similarity' => 0, 'flagged' => false, 'explanation' => 'None.'])
    ))->toThrow(HttpException::class);
});
