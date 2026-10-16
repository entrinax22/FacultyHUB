<?php

use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use App\Services\AiProcessingRunService;
use Symfony\Component\HttpKernel\Exception\HttpException;

function createAiProcessingRunFixture(): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create([
        'name' => 'First Semester',
        'school_year' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'AI301', 'name' => 'AI Processing']);
    $section = Section::create([
        'name' => 'BSCS 3A',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'title' => 'Resumable Processing',
        'instructions' => 'Submit an essay.',
        'type' => 'essay',
        'max_score' => 20,
        'is_published' => true,
    ]);

    return compact('faculty', 'assignment');
}

test('creates one active run per assignment and processing type', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiProcessingRunFixture();
    $service = app(AiProcessingRunService::class);

    $first = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_PLAGIARISM, 3);
    $second = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_PLAGIARISM, 3);

    expect($second->id)->toBe($first->id)
        ->and(AiProcessingRun::query()->count())->toBe(1)
        ->and($first->status)->toBe(AiProcessingRun::STATUS_PENDING);
});

test('rejects runs for another faculty members assignment', function () {
    ['assignment' => $assignment] = createAiProcessingRunFixture();
    $otherFaculty = User::factory()->create(['role' => 'faculty']);

    expect(fn () => app(AiProcessingRunService::class)->startAssignmentRun(
        $otherFaculty,
        $assignment,
        AiProcessingRun::TYPE_GRADING,
        1
    ))->toThrow(HttpException::class);
});

test('claims bounded work and tracks completion without retaining a lease', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiProcessingRunFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_GRADING, 2);

    $firstLease = $service->claim($run, $faculty);
    $run = $service->recordProgress($run, $faculty, $firstLease, 1);

    expect($run->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($run->completed_work_units)->toBe(1)
        ->and($run->lease_token)->toBeNull();

    $secondLease = $service->claim($run, $faculty);
    $run = $service->recordProgress($run, $faculty, $secondLease, 1);

    expect($run->status)->toBe(AiProcessingRun::STATUS_COMPLETED)
        ->and($run->completed_work_units)->toBe(2)
        ->and($run->active_key)->toBeNull()
        ->and($run->completed_at)->not->toBeNull();
});

test('only one request can claim an active run lease', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiProcessingRunFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_GRADING, 1);

    expect($service->claim($run, $faculty))->toBeString()
        ->and($service->claim($run->fresh(), $faculty))->toBeNull();
});

test('expired leases are recovered for a later request', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiProcessingRunFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_GRADING, 1);
    $service->claim($run, $faculty);
    $run->fresh()->update(['lease_expires_at' => now()->subSecond()]);

    expect($service->recoverExpiredRuns())->toBe(1);

    $run = $run->fresh();
    expect($run->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($run->lease_token)->toBeNull()
        ->and($service->claim($run, $faculty))->toBeString();
});

test('retry limits fail the run with a safe error code', function () {
    config()->set('ai_processing.max_retry_attempts', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiProcessingRunFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->startAssignmentRun($faculty, $assignment, AiProcessingRun::TYPE_GRADING, 2);
    $lease = $service->claim($run, $faculty);

    $run = $service->recordFailure($run, $faculty, $lease, 'provider_rate_limited');

    expect($run->status)->toBe(AiProcessingRun::STATUS_FAILED)
        ->and($run->error_code)->toBe('provider_rate_limited')
        ->and($run->active_key)->toBeNull()
        ->and($service->claim($run, $faculty))->toBeNull();
});

test('processing limits use conservative configurable defaults', function () {
    $limits = app(AiProcessingRunService::class)->limits();

    expect($limits)->toMatchArray([
        'work_units_per_request' => 1,
        'max_input_bytes' => 24000,
        'max_retry_attempts' => 3,
        'timeout_budget_seconds' => 25,
    ]);
});
