<?php

use App\Models\AiFeedback;
use App\Models\AiGradingRunSubmission;
use App\Models\AiProcessingBudgetWindow;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Services\AIGraderService;
use App\Services\AiProcessingRunService;
use Illuminate\Support\Facades\Http;

function createGradingProcessingFixture(string $type = 'essay'): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create(['user_id' => $studentUser->id, 'student_no' => fake()->unique()->numerify('G-####'), 'first_name' => 'AI', 'last_name' => 'Student', 'email' => $studentUser->email, 'course' => 'CS', 'year_level' => 1]);
    $semester = Semester::create(['name' => 'AI Term', 'school_year' => '2026', 'start_date' => now(), 'end_date' => now()->addMonth(), 'is_active' => true]);
    $section = Section::create(['name' => 'AI', 'semester_id' => $semester->id, 'subject_id' => Subject::create(['code' => fake()->unique()->bothify('AI###'), 'name' => 'AI Processing'])->id, 'faculty_id' => $faculty->id]);
    $assignment = Assignment::create(['section_id' => $section->id, 'title' => 'AI Work', 'instructions' => 'Complete the work.', 'rubric' => 'Accuracy', 'type' => $type, 'language' => $type === 'code' ? 'php' : null, 'max_score' => 20]);
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'content' => $type === 'code' ? '<?php return true;' : 'A concise essay.', 'status' => 'pending']);

    return compact('faculty', 'assignment', 'submission');
}

test('AI grading run submissions preserve an immutable work snapshot and safe result state', function () {
    $owner = User::factory()->create(['role' => 'faculty']);
    $run = AiProcessingRun::create([
        'owner_id' => $owner->id,
        'resource_type' => 'assignment',
        'resource_id' => 1,
        'processing_type' => AiProcessingRun::TYPE_GRADING,
        'status' => AiProcessingRun::STATUS_PENDING,
        'total_work_units' => 1,
        'max_retry_attempts' => 3,
        'active_key' => 'grading-test-run',
    ]);

    $work = AiGradingRunSubmission::create([
        'ai_processing_run_id' => $run->id,
        'submission_id' => 99,
        'content' => 'Immutable student submission snapshot.',
        'content_hash' => hash('sha256', 'Immutable student submission snapshot.'),
        'status' => AiGradingRunSubmission::STATUS_PENDING,
        'result' => ['score' => 18, 'overall_comment' => 'Validated feedback.'],
    ]);

    expect($work->fresh()->result)->toBe([
        'score' => 18,
        'overall_comment' => 'Validated feedback.',
    ])->and($run->gradingSubmissions()->first()?->id)->toBe($work->id);
});

test('grading preparation snapshots eligible submissions idempotently and excludes approved work', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create(['user_id' => $studentUser->id, 'student_no' => 'S-200', 'first_name' => 'Test', 'last_name' => 'Student', 'email' => $studentUser->email, 'course' => 'CS', 'year_level' => 1]);
    $semester = Semester::create(['name' => 'One', 'school_year' => '2026', 'start_date' => now(), 'end_date' => now()->addMonth(), 'is_active' => true]);
    $section = Section::create(['name' => 'A', 'semester_id' => $semester->id, 'subject_id' => Subject::create(['code' => 'CS200', 'name' => 'Code'])->id, 'faculty_id' => $faculty->id]);
    Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'semester_id' => $semester->id, 'status' => 'active']);
    $assignment = Assignment::create(['section_id' => $section->id, 'title' => 'Essay', 'instructions' => 'Write.', 'type' => 'essay', 'max_score' => 20]);
    $eligible = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'content' => 'Snapshot this work.', 'status' => 'pending']);
    $approvedUser = User::factory()->create(['role' => 'student']);
    $approvedStudent = Student::create(['user_id' => $approvedUser->id, 'student_no' => 'S-201', 'first_name' => 'Approved', 'last_name' => 'Student', 'email' => $approvedUser->email, 'course' => 'CS', 'year_level' => 1]);
    $approved = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $approvedStudent->id, 'content' => 'Do not snapshot.', 'status' => 'approved']);

    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);
    $again = $service->prepareGradingRun($faculty, $assignment);

    expect($again->id)->toBe($run->id)
        ->and($run->fresh()->total_work_units)->toBe(1)
        ->and($run->gradingSubmissions()->count())->toBe(1);
    $this->assertDatabaseHas('ai_grading_run_submissions', ['submission_id' => $eligible->id, 'content_hash' => hash('sha256', 'Snapshot this work.')]);
    $this->assertDatabaseMissing('ai_grading_run_submissions', ['submission_id' => $approved->id]);
});

test('grading work claims one pending snapshot and safely recovers an expired lease', function () {
    $owner = User::factory()->create(['role' => 'faculty']);
    $run = AiProcessingRun::create(['owner_id' => $owner->id, 'resource_type' => 'assignment', 'resource_id' => 10, 'processing_type' => AiProcessingRun::TYPE_GRADING, 'status' => AiProcessingRun::STATUS_PENDING, 'total_work_units' => 2, 'max_retry_attempts' => 3, 'active_key' => 'grading-claim-run']);
    foreach ([101, 102] as $id) {
        AiGradingRunSubmission::create(['ai_processing_run_id' => $run->id, 'submission_id' => $id, 'content' => "Work {$id}", 'content_hash' => hash('sha256', "Work {$id}"), 'status' => AiGradingRunSubmission::STATUS_PENDING]);
    }

    $service = app(AiProcessingRunService::class);
    $first = $service->claimNextGradingSubmission($run, $owner);
    $second = $service->claimNextGradingSubmission($run->fresh(), $owner);

    expect($first?->status)->toBe(AiGradingRunSubmission::STATUS_PROCESSING)
        ->and($second)->toBeNull();

    $first->update(['lease_expires_at' => now()->subSecond()]);
    $run->update(['lease_expires_at' => now()->subSecond()]);
    $reclaimed = $service->claimNextGradingSubmission($run->fresh(), $owner);

    expect($reclaimed?->id)->toBe($first->id)
        ->and($reclaimed?->lease_token)->not->toBe($first->lease_token);
});

test('grading processing persists validated essay and code suggestions one item per action', function (string $type, array $result) {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode($result)]]]]],
    ])]);
    ['faculty' => $faculty, 'assignment' => $assignment, 'submission' => $submission] = createGradingProcessingFixture($type);
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));

    expect($processed->status)->toBe(AiProcessingRun::STATUS_COMPLETED)
        ->and($processed->completed_work_units)->toBe(1)
        ->and($submission->fresh()->status)->toBe('graded')
        ->and(AiFeedback::where('submission_id', $submission->id)->value('score'))->toBe(18.0);
    Http::assertSentCount(1);
})->with([
    'essay' => ['essay', ['score' => 18, 'overall_comment' => 'Good.', 'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']]]],
    'code' => ['code', ['score' => 18, 'overall_comment' => 'Good.', 'correctness' => 90, 'logic_quality' => 90, 'code_quality' => 90, 'inline_comments' => [['line' => 1, 'comment' => 'Clear.']]]],
]);

test('provider 503 uses bounded grading backoff and does not consume plagiarism budget', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    Http::fake(['*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));
    $work = $processed->gradingSubmissions()->first();

    expect($processed->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($processed->error_code)->toBe('provider_unavailable')
        ->and($work->status)->toBe(AiGradingRunSubmission::STATUS_PENDING)
        ->and($work->attempt_count)->toBe(1)
        ->and($work->next_eligible_at->between(now()->addSeconds(25), now()->addSeconds(35)))->toBeTrue()
        ->and(AiProcessingBudgetWindow::where('category', 'grading')->count())->toBe(2)
        ->and(AiProcessingBudgetWindow::where('category', 'plagiarism')->count())->toBe(0);
    Http::assertSentCount(1);
});

test('grading budget exhaustion defers without consuming an attempt or calling Gemini', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_daily_request_budget', 1);
    config()->set('ai_processing.grading_daily_input_token_budget', 1);
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));
    $work = $processed->gradingSubmissions()->first();

    expect($processed->error_code)->toBe('budget_exhausted')
        ->and($work->status)->toBe(AiGradingRunSubmission::STATUS_PENDING)
        ->and($work->attempt_count)->toBe(0);
    Http::assertNothingSent();
});

test('three provider failures become terminal and an authorized retry safely reopens only failed work', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    Http::fake(['*' => Http::response([], 503)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    foreach ([1, 2, 3] as $attempt) {
        $run = $service->processNextGradingSubmission($run->fresh(), $faculty, app(AIGraderService::class));
        if ($attempt < 3) {
            $run->gradingSubmissions()->update(['next_eligible_at' => now()->subSecond()]);
        }
    }

    $work = $run->gradingSubmissions()->first();
    expect($run->status)->toBe(AiProcessingRun::STATUS_FAILED)
        ->and($work->status)->toBe(AiGradingRunSubmission::STATUS_FAILED)
        ->and($work->attempt_count)->toBe(3);

    $retried = $service->retryFailedGradingRun($run, $faculty);
    expect($retried->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($retried->retry_count)->toBe(1)
        ->and($retried->gradingSubmissions()->first()->attempt_count)->toBe(0);
    Http::assertSentCount(3);
});

test('a changed submission is rejected after Gemini returns and no suggestion is persisted', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    ['faculty' => $faculty, 'assignment' => $assignment, 'submission' => $submission] = createGradingProcessingFixture();
    Http::fake(function () use ($submission) {
        $submission->update(['content' => 'Changed while processing.']);
        $result = [
            'score' => 18,
            'overall_comment' => 'Good.',
            'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
        ];

        return Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($result)]]]]],
        ]);
    });
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));

    expect($processed->status)->toBe(AiProcessingRun::STATUS_FAILED)
        ->and($processed->error_code)->toBe('submission_changed')
        ->and(AiFeedback::where('submission_id', $submission->id)->exists())->toBeFalse();
});

test('network connection failures defer safely and consume one attempt', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    Http::fake(['*' => Http::failedConnection()]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));
    $work = $processed->gradingSubmissions()->first();

    expect($processed->status)->toBe(AiProcessingRun::STATUS_PENDING)
        ->and($processed->error_code)->toBe('provider_request_failed')
        ->and($work->attempt_count)->toBe(1)
        ->and($work->next_eligible_at)->not->toBeNull();
    Http::assertSentCount(1);
});

test('deleted submissions cannot receive late AI suggestions', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    ['faculty' => $faculty, 'assignment' => $assignment, 'submission' => $submission] = createGradingProcessingFixture();
    Http::fake(function () use ($submission) {
        $submission->delete();

        return Http::response(validEssayProviderResponse());
    });
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));

    expect($processed->status)->toBe(AiProcessingRun::STATUS_FAILED)
        ->and($processed->error_code)->toBe('submission_deleted')
        ->and(AiFeedback::query()->count())->toBe(0);
});

test('faculty approval or manual grading during processing protects the grade', function (string $decision) {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    ['faculty' => $faculty, 'assignment' => $assignment, 'submission' => $submission] = createGradingProcessingFixture();
    Http::fake(function () use ($decision, $submission, $assignment) {
        if ($decision === 'approval') {
            $submission->update(['status' => 'approved']);
        } else {
            Grade::create([
                'submission_id' => $submission->id,
                'student_id' => $submission->student_id,
                'section_id' => $assignment->section_id,
                'assignment_id' => $assignment->id,
                'raw_score' => 15,
                'max_score' => 20,
                'is_released' => false,
            ]);
        }

        return Http::response(validEssayProviderResponse());
    });
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);

    $processed = $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class));

    expect($processed->error_code)->toBe('grade_protected')
        ->and(AiFeedback::where('submission_id', $submission->id)->exists())->toBeFalse();
    if ($decision === 'manual grade') {
        expect(Grade::where('submission_id', $submission->id)->value('raw_score'))->toBe(15.0);
    }
})->with(['approval', 'manual grade']);

test('cancellation during a provider request prevents late result persistence', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    ['faculty' => $faculty, 'assignment' => $assignment, 'submission' => $submission] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);
    Http::fake(function () use ($service, $run, $faculty) {
        $service->cancelGradingRun($run->fresh(), $faculty);

        return Http::response(validEssayProviderResponse());
    });

    expect(fn () => $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class)))
        ->toThrow(RuntimeException::class, 'AI processing lease is no longer valid.');
    expect($run->fresh()->error_code)->toBe('cancelled')
        ->and(AiFeedback::where('submission_id', $submission->id)->exists())->toBeFalse();
});

test('an active lease prevents duplicate grading requests', function () {
    config()->set('services.gemini.key', 'test-key');
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);
    $claimed = $service->claimNextGradingSubmission($run, $faculty);

    $duplicate = $service->processNextGradingSubmission($run->fresh(), $faculty, app(AIGraderService::class));

    expect($claimed)->not->toBeNull()
        ->and($duplicate->status)->toBe(AiProcessingRun::STATUS_PROCESSING);
    Http::assertNothingSent();
});

test('a mismatched grading lease rejects completion without mutating grades', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 10);
    ['faculty' => $faculty, 'assignment' => $assignment, 'submission' => $submission] = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $run = $service->prepareGradingRun($faculty, $assignment);
    Http::fake(function () use ($run) {
        $run->fresh()->update(['lease_token' => str_repeat('x', 64)]);

        return Http::response(validEssayProviderResponse());
    });

    expect(fn () => $service->processNextGradingSubmission($run, $faculty, app(AIGraderService::class)))
        ->toThrow(RuntimeException::class, 'AI processing lease is no longer valid.');
    expect(AiFeedback::where('submission_id', $submission->id)->exists())->toBeFalse()
        ->and(Grade::where('submission_id', $submission->id)->exists())->toBeFalse();
});

test('atomic grading budget counters prevent a second request at the minute limit', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 1);
    Http::fake(['*' => Http::response(validEssayProviderResponse())]);
    $first = createGradingProcessingFixture();
    $second = createGradingProcessingFixture();
    $service = app(AiProcessingRunService::class);
    $firstRun = $service->prepareGradingRun($first['faculty'], $first['assignment']);
    $secondRun = $service->prepareGradingRun($second['faculty'], $second['assignment']);

    $service->processNextGradingSubmission($firstRun, $first['faculty'], app(AIGraderService::class));
    $deferred = $service->processNextGradingSubmission($secondRun, $second['faculty'], app(AIGraderService::class));

    expect($deferred->error_code)->toBe('budget_exhausted')
        ->and($deferred->gradingSubmissions()->first()->attempt_count)->toBe(0)
        ->and(AiProcessingBudgetWindow::where('category', 'grading')->where('window_type', 'minute')->value('request_count'))->toBe(1)
        ->and(AiProcessingBudgetWindow::where('category', 'plagiarism')->exists())->toBeFalse();
    Http::assertSentCount(1);
});

function validEssayProviderResponse(): array
{
    $result = [
        'score' => 18,
        'overall_comment' => 'Good.',
        'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
    ];

    return ['candidates' => [['content' => ['parts' => [['text' => json_encode($result)]]]]]];
}
