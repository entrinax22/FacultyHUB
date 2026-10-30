<?php

use App\Models\AiFeedback;
use App\Models\AiGradingRunSubmission;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Jobs\ProcessAiGradingRun;
use App\Jobs\CheckPlagiarismJob;
use App\Services\AiProcessingRunService;
use App\Services\AIGraderService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.connection', null);
    config()->set('queue.connections.database.after_commit', false);
});

function createAiGradingApiFixture(int $submissionCount = 2): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create(['name' => 'AI API', 'school_year' => '2026', 'start_date' => now(), 'end_date' => now()->addMonth(), 'is_active' => true]);
    $subject = Subject::create(['code' => fake()->unique()->bothify('API###'), 'name' => 'AI API']);
    $section = Section::create(['name' => 'API', 'semester_id' => $semester->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty->id]);
    $assignment = Assignment::create(['section_id' => $section->id, 'title' => 'Essay', 'instructions' => 'Write.', 'rubric' => 'Accuracy', 'type' => 'essay', 'max_score' => 20]);

    foreach (range(1, $submissionCount) as $index) {
        $studentUser = User::factory()->create(['role' => 'student']);
        $student = Student::create(['user_id' => $studentUser->id, 'student_no' => fake()->unique()->numerify('API-####'), 'first_name' => 'API', 'last_name' => "Student {$index}", 'email' => $studentUser->email, 'course' => 'CS', 'year_level' => 1]);
        Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'content' => "Essay {$index}", 'status' => 'pending']);
    }

    return compact('faculty', 'assignment');
}

function aiGradingPath(Assignment $assignment, string $action): string
{
    return '/assignments/'.Crypt::encryptString((string) $assignment->id).'/ai-grading/'.$action;
}

function fakeSuccessfulAiGrade(): void
{
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 20);
    $result = [
        'score' => 18,
        'overall_comment' => 'Good.',
        'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
    ];
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode($result)]]]]],
    ])]);
}

function individualGradingPayload(Assignment $assignment): array
{
    return ['mode' => 'individual', 'submission_id' => Crypt::encryptString((string) $assignment->submissions()->firstOrFail()->id)];
}

function runOneAiGradingJob(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'ai-grading', '--once' => true, '--sleep' => 0]);
}

test('the configured supervisor queue list also processes default queue jobs', function () {
    Http::fake();
    ['assignment' => $assignment] = createAiGradingApiFixture(1);
    \Illuminate\Support\Facades\Queue::connection('database')->pushOn('default', new CheckPlagiarismJob($assignment));
    expect(DB::table('jobs')->where('queue', 'default')->count())->toBe(1);
    preg_match('/^command=.*--queue=([^\s]+)/m', file_get_contents(base_path('docker/supervisord.conf')), $matches);
    expect($matches[1] ?? null)->not->toBeNull();
    Artisan::call('queue:work', ['connection' => 'database', '--queue' => $matches[1], '--once' => true, '--sleep' => 0]);
    expect(DB::table('jobs')->where('queue', 'default')->count())->toBe(0);
    Http::assertNothingSent();
});

test('owning faculty and administrators can start batch grading while students and other faculty are denied', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $student = User::factory()->create(['role' => 'student']);
    $path = aiGradingPath($assignment, 'start');

    $this->actingAs($faculty)->postJson($path, ['mode' => 'batch'])
        ->assertOk()->assertJsonPath('data.preparation_status', 'pending');
    $this->actingAs($admin)->postJson($path, ['mode' => 'batch'])->assertOk();
    $this->actingAs($otherFaculty)->postJson($path, ['mode' => 'batch'])->assertForbidden();
    $this->actingAs($student)->postJson($path, ['mode' => 'batch'])->assertForbidden();
});

test('individual starts use encrypted submission IDs and reject invalid or unrelated IDs', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture();
    $submission = $assignment->submissions()->firstOrFail();

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), [
        'mode' => 'individual',
        'submission_id' => Crypt::encryptString((string) $submission->id),
    ])->assertOk()->assertJsonPath('data.total_submissions', 1);

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), [
        'mode' => 'individual', 'submission_id' => 'invalid',
    ])->assertNotFound();

    ['assignment' => $otherAssignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), [
        'mode' => 'individual',
        'submission_id' => Crypt::encryptString((string) $otherAssignment->submissions()->value('id')),
    ])->assertNotFound();
});

test('duplicate starts reuse one active run and status exposes only safe progress fields', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture();
    $path = aiGradingPath($assignment, 'start');
    $this->actingAs($faculty)->postJson($path, ['mode' => 'batch'])->assertOk();
    $this->actingAs($faculty)->postJson($path, ['mode' => 'batch'])->assertOk();
    $response = $this->actingAs($faculty)->getJson(aiGradingPath($assignment, 'status'))->assertOk();

    expect(AiProcessingRun::query()->count())->toBe(1);
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);
    $response->assertJsonStructure(['success', 'message', 'data' => [
        'status', 'total_submissions', 'pending_count', 'processing_count', 'completed_count',
        'failed_count', 'progress_percentage', 'next_eligible_at', 'can_continue', 'can_retry',
        'can_cancel', 'error_code', 'error_message',
    ]]);
    expect($response->json('data'))->not->toHaveKeys(['id', 'lease_token', 'content']);
});

test('an individual start cannot alter a batch that is still preparing', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $this->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertUnprocessable();
    expect(AiProcessingRun::sole()->preparation_status)->toBe('pending')
        ->and(AiProcessingRun::sole()->gradingSubmissions()->count())->toBe(0);
});

test('a batch can be cancelled before preparation and its queued job becomes stale', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $this->postJson(aiGradingPath($assignment, 'cancel'))->assertOk()
        ->assertJsonPath('data.status', 'failed')->assertJsonPath('data.error_code', 'cancelled');
    runOneAiGradingJob();
    expect(AiFeedback::count())->toBe(0)->and(AiProcessingRun::sole()->active_key)->toBeNull();
    Http::assertNothingSent();
});

test('invalid encrypted assignment identifiers return not found', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)->getJson('/assignments/invalid/ai-grading/status')->assertNotFound();
});

test('process next grades exactly one item and never mutates final grades', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture();
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertOk();
    fakeSuccessfulAiGrade();

    $response = $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'next'));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.outcome', 'completed')
        ->assertJsonPath('data.completed_count', 1)
        ->assertJsonPath('data.pending_count', 0);
    expect(AiFeedback::query()->count())->toBe(1)
        ->and(Grade::query()->count())->toBe(0);
    Http::assertSentCount(1);
});

test('provider 503 and 429 responses are deferred without false success', function (int $status, string $code) {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 20);
    Http::fake(['*' => Http::response([], $status)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertOk();

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'next'))
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.outcome', 'deferred')
        ->assertJsonPath('data.error_code', $code)
        ->assertJsonPath('data.can_continue', false);
})->with([[503, 'provider_unavailable'], [429, 'provider_rate_limited']]);

test('budget exhaustion defers without a provider request', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_daily_input_token_budget', 1);
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertOk();

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'next'))
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.outcome', 'deferred')
        ->assertJsonPath('data.error_code', 'budget_exhausted');
    Http::assertNothingSent();
});

test('terminal failures can be retried and active runs can be cancelled', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 20);
    Http::fake(['*' => Http::response([], 503)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertOk();
    foreach ([1, 2, 3] as $attempt) {
        $response = $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'next'));
        if ($attempt < 3) {
            AiProcessingRun::query()->sole()->gradingSubmissions()->update(['next_eligible_at' => now()->subSecond()]);
        }
    }
    $response->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.can_retry', true);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'retry'))
        ->assertOk()->assertJsonPath('data.status', 'pending');
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'cancel'))
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'cancelled')
        ->assertJsonPath('data.can_retry', false);
});

test('approved and released work is excluded from API grading runs', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $submissions = $assignment->submissions()->orderBy('id')->get();
    $submissions[0]->update(['status' => 'approved']);
    Grade::create(['submission_id' => $submissions[1]->id, 'student_id' => $submissions[1]->student_id, 'section_id' => $assignment->section_id, 'assignment_id' => $assignment->id, 'raw_score' => 19, 'max_score' => 20, 'is_released' => true]);

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])
        ->assertOk()->assertJsonPath('data.preparation_status', 'pending');
    runOneAiGradingJob();
    $this->actingAs($faculty)->getJson(aiGradingPath($assignment, 'status'))
        ->assertOk()->assertJsonPath('data.total_submissions', 0)->assertJsonPath('data.status', 'completed');
    expect(AiFeedback::query()->count())->toBe(0);
});

test('batch run and database queue insertion roll back together', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    try {
        DB::transaction(function () use ($faculty, $assignment) {
            app(AiProcessingRunService::class)->startQueuedGradingBatch($faculty, $assignment);
            throw new RuntimeException('rollback test');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback test');
    }
    expect(AiProcessingRun::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
});

test('one queued job prepares and grades one submission then schedules the next', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])
        ->assertOk()->assertJsonPath('data.total_submissions', 0)->assertJsonPath('data.queued', true);
    Http::assertNothingSent();

    runOneAiGradingJob();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.total_submissions', 2)->assertJsonPath('data.completed_count', 1)
        ->assertJsonPath('data.pending_count', 1);
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);
    runOneAiGradingJob();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.completed_count', 2);
    expect(AiFeedback::count())->toBe(2)->and(Grade::count())->toBe(0);
    Http::assertSentCount(2);
});

test('stale coordinator generations never call the provider', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $run = app(AiProcessingRunService::class)->startQueuedGradingBatch($faculty, $assignment);
    $stale = new ProcessAiGradingRun($run->id, $run->queue_generation);
    $run->increment('queue_generation');
    $stale->handle(app(AiProcessingRunService::class), app(AIGraderService::class));
    expect($run->fresh()->gradingSubmissions()->count())->toBe(0);
    Http::assertNothingSent();
});

test('duplicate delivery during an active provider request cannot duplicate grading or leave a completed run queued', function () {
    config()->set('services.gemini.key', 'test-key');
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    Http::fake(function () {
        $run = AiProcessingRun::sole();
        (new ProcessAiGradingRun($run->id, $run->queue_generation))->handle(
            app(AiProcessingRunService::class), app(AIGraderService::class)
        );
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'score' => 18, 'overall_comment' => 'Good.',
            'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
        ])]]]]]]);
    });
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.queued', false);
    expect(AiFeedback::count())->toBe(1);
    Http::assertSentCount(1);
});

test('explicit rate limits schedule a delayed retry that processes after it becomes eligible', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 20);
    Http::fakeSequence()->push([], 429)->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
        'score' => 18, 'overall_comment' => 'Good.',
        'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
    ])]]]]]]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $run = AiProcessingRun::sole();
    expect($run->error_code)->toBe('provider_rate_limited')
        ->and($run->gradingSubmissions()->sole()->next_eligible_at)->not->toBeNull()
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->where('available_at', '>', time())->count())->toBe(1);
    Http::assertSentCount(1);
    $run->gradingSubmissions()->update(['next_eligible_at' => now()->subSecond()]);
    DB::table('jobs')->where('queue', 'ai-grading')->update(['available_at' => time() - 1]);
    runOneAiGradingJob();
    expect($run->fresh()->status)->toBe('completed')->and(AiFeedback::count())->toBe(1);
    Http::assertSentCount(2);
});

test('unsafe queue configuration prevents batch creation', function (string $key, mixed $value) {
    config()->set($key, $value);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    expect(fn () => app(AiProcessingRunService::class)->startQueuedGradingBatch($faculty, $assignment))
        ->toThrow(RuntimeException::class);
    expect(AiProcessingRun::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
})->with([
    ['queue.connections.database.retry_after', 150],
    ['ai_processing.grading_queue_lease_seconds', 120],
    ['ai_processing.timeout_budget_seconds', 120],
    ['queue.connections.database.connection', 'other'],
    ['queue.connections.database.after_commit', true],
]);

test('safe timeout and matching database connection overrides permit queueing', function () {
    config()->set('queue.connections.database.connection', DB::getDefaultConnection());
    config()->set('queue.connections.database.retry_after', 200);
    config()->set('ai_processing.grading_queue_lease_seconds', 160);
    config()->set('ai_processing.timeout_budget_seconds', 30);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $run = app(AiProcessingRunService::class)->startQueuedGradingBatch($faculty, $assignment);
    expect($run->queue_job_id)->not->toBeNull()->and(DB::table('jobs')->where('id', $run->queue_job_id)->exists())->toBeTrue();
});

test('rate limits stop automatically after the configured attempt limit', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.max_retry_attempts', 1);
    Http::fake(['*' => Http::response([], 429)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'provider_rate_limited');
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
    Http::assertSentCount(1);
});

test('ambiguous provider failures pause the batch until an authorized retry', function () {
    config()->set('services.gemini.key', 'test-key');
    Http::fake(['*' => Http::response([], 503)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'failed')->assertJsonPath('data.error_code', 'outcome_unknown')
        ->assertJsonPath('data.outcome_unknown_count', 1)->assertJsonPath('data.can_retry', true);
    $this->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertUnprocessable();
    $this->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertUnprocessable();
    $this->postJson(aiGradingPath($assignment, 'retry'))->assertUnprocessable()->assertJsonValidationErrors('confirm_unknown');
    $this->postJson(aiGradingPath($assignment, 'retry'), ['confirm_unknown' => true])->assertOk()->assertJsonPath('data.status', 'pending');
    Http::assertSentCount(1);
});

test('a provider connection timeout is recorded as an unknown outcome', function () {
    config()->set('services.gemini.key', 'test-key');
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('simulated timeout'));
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'failed')->assertJsonPath('data.error_code', 'outcome_unknown');
    expect(AiProcessingRun::sole()->gradingSubmissions()->sole()->attempt_count)->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
});

test('expired in-flight work becomes unknown without another provider call', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $work = $run->gradingSubmissions()->sole();
    $run->update(['status' => 'processing', 'lease_token' => 'old', 'lease_expires_at' => now()->subMinute()]);
    $work->update(['status' => 'processing', 'error_code' => 'provider_in_flight', 'lease_token' => 'old', 'lease_expires_at' => now()->subMinute()]);
    (new ProcessAiGradingRun($run->id, $run->queue_generation))->handle($service, app(AIGraderService::class));
    expect($work->fresh()->error_code)->toBe('outcome_unknown')->and($run->fresh()->status)->toBe('failed');
    Http::assertNothingSent();
});

test('a missing queue job can be resumed without duplicate dispatch', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    DB::table('jobs')->where('queue', 'ai-grading')->delete();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.can_resume', true);
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1)
        ->and(AiProcessingRun::sole()->queue_generation)->toBe(2);
});

test('an exhausted coordinator exposes a recoverable queue failure', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = AiProcessingRun::sole();
    DB::table('jobs')->where('id', $run->queue_job_id)->delete();
    (new ProcessAiGradingRun($run->id, $run->queue_generation))->failed(new RuntimeException('worker stopped'));
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.error_code', 'queue_failed')
        ->assertJsonPath('data.can_resume', true);
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk()->assertJsonPath('data.queued', true);
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);
});

test('a missing queue job cannot be resumed while its processing lease is still active', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = AiProcessingRun::sole();
    DB::table('jobs')->where('id', $run->queue_job_id)->delete();
    $run->update(['queue_job_id' => null, 'status' => 'processing', 'lease_token' => 'active', 'lease_expires_at' => now()->addMinute()]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.can_resume', false)->assertJsonPath('data.stalled', true);
    $run->update(['lease_expires_at' => now()->subSecond()]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()->assertJsonPath('data.can_resume', true);
});

test('a released database job recovers after an unexpected worker exception', function () {
    Http::fake([]);
    Http::preventStrayRequests();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('toPlainText')->andReturn('text');
    $ai->shouldReceive('modelUsed')->andThrow(new RuntimeException('simulated worker failure'));
    app()->instance(AIGraderService::class, $ai);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();

    $run = AiProcessingRun::sole();
    expect($run->status)->toBe('processing')
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1)
        ->and(DB::table('jobs')->where('id', $run->queue_job_id)->exists())->toBeFalse();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.stalled', true)->assertJsonPath('data.can_resume', false);
    Http::assertNothingSent();

    $run->update(['lease_expires_at' => now()->subSecond()]);
    $run->gradingSubmissions()->update(['lease_expires_at' => now()->subSecond()]);
    app()->forgetInstance(AIGraderService::class);
    fakeSuccessfulAiGrade();
    runOneAiGradingJob();
    expect($run->fresh()->error_code)->toBeNull();
    expect($run->fresh()->status)->toBe('completed')->and(AiFeedback::count())->toBe(1);
    Http::assertSentCount(1);
});

test('budget exhaustion pauses the queued batch without contacting the provider', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_daily_input_token_budget', 1);
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $response = $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.error_code', 'budget_exhausted')->assertJsonPath('data.can_resume', true)
        ->assertJsonPath('data.budget_reason', 'budget_daily_tokens');
    expect(\Carbon\Carbon::parse($response->json('data.budget_reset_at'))->equalTo(now('America/Los_Angeles')->addDay()->startOfDay()))->toBeTrue();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
    Http::assertNothingSent();
});

test('minute budget schedules one fenced continuation at the next window', function () {
    $this->travelTo(now()->startOfMinute()->addSeconds(10));
    fakeSuccessfulAiGrade();
    config()->set('ai_processing.grading_requests_per_minute', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    runOneAiGradingJob();
    $run = AiProcessingRun::sole()->fresh();
    $reset = now()->copy()->addMinute()->startOfMinute();
    $job = DB::table('jobs')->where('queue', 'ai-grading')->sole();
    expect($job->available_at)->toBe($reset->timestamp)
        ->and($run->error_code)->toBe('budget_waiting')
        ->and($run->gradingSubmissions()->where('status', 'pending')->sole()->attempt_count)->toBe(0);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.budget_reason', 'budget_minute_requests')
        ->assertJsonPath('data.can_resume', false);
    $generation = $run->queue_generation;
    (new ProcessAiGradingRun($run->id, $generation))->handle(app(AiProcessingRunService::class), app(AIGraderService::class));
    (new ProcessAiGradingRun($run->id, $generation - 1))->handle(app(AiProcessingRunService::class), app(AIGraderService::class));
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1)
        ->and($run->fresh()->queue_generation)->toBe($generation);
    Http::assertSentCount(1);
    $this->travelTo($reset);
    runOneAiGradingJob();
    expect($run->fresh()->status)->toBe('completed')->and(AiFeedback::count())->toBe(2)
        ->and(DB::table('ai_processing_budget_windows')->where('category', 'grading')->where('window_type', 'daily')->value('request_count'))->toBe(2);
    Http::assertSentCount(2);
});

test('daily request budget pauses until an authorized resume', function () {
    fakeSuccessfulAiGrade();
    config()->set('ai_processing.grading_daily_request_budget', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    runOneAiGradingJob();
    $response = $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.budget_reason', 'budget_daily_requests')->assertJsonPath('data.can_resume', true);
    expect(\Carbon\Carbon::parse($response->json('data.budget_reset_at'))->equalTo(now('America/Los_Angeles')->addDay()->startOfDay()))->toBeTrue();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
    Http::assertSentCount(1);
    config()->set('ai_processing.grading_daily_request_budget', 2);
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);
    runOneAiGradingJob();
    expect(AiProcessingRun::sole()->status)->toBe('completed');
    Http::assertSentCount(2);
});

test('global expired-run recovery preserves unknown grading outcomes', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $work = $run->gradingSubmissions()->sole();
    $run->update(['status' => 'processing', 'lease_token' => 'old', 'lease_expires_at' => now()->subMinute()]);
    $work->update(['status' => 'processing', 'error_code' => 'provider_in_flight', 'lease_token' => 'old', 'lease_expires_at' => now()->subMinute()]);
    $service->recoverExpiredRuns();
    expect($run->fresh()->status)->toBe('failed')->and($work->fresh()->error_code)->toBe('outcome_unknown');
});

test('manual approval during the provider request is protected from AI completion', function () {
    config()->set('services.gemini.key', 'test-key');
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $submission = $assignment->submissions()->firstOrFail();
    $this->actingAs($faculty);
    Http::fake(function () use ($submission) {
        $this->postJson(route('submissions.approve', Crypt::encryptString((string) $submission->id)), ['raw_score' => 17])->assertOk();
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'score' => 18, 'overall_comment' => 'Good.',
            'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
        ])]]]]]]);
    });
    $this->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    expect($submission->fresh()->status)->toBe('approved')->and($submission->grade()->first()->raw_score)->toEqual(17)
        ->and(AiFeedback::count())->toBe(0)
        ->and(AiProcessingRun::sole()->gradingSubmissions()->sole()->error_code)->toBe('grade_protected');
});

test('approval before a queued attempt prevents the provider request', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    app(AiProcessingRunService::class)->prepareGradingRun($faculty, $assignment);
    $submission = $assignment->submissions()->firstOrFail();
    $this->postJson(route('submissions.approve', Crypt::encryptString((string) $submission->id)), ['raw_score' => 17])->assertOk();
    runOneAiGradingJob();
    expect($submission->fresh()->status)->toBe('approved')
        ->and(AiFeedback::count())->toBe(0)
        ->and(AiProcessingRun::sole()->gradingSubmissions()->sole()->error_code)->toBe('grade_protected');
    Http::assertNothingSent();
});

test('other faculty cannot resume or retry a batch they do not own', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $other = User::factory()->create(['role' => 'faculty']);
    $this->actingAs($other)->postJson(aiGradingPath($assignment, 'next'))->assertForbidden();
    $this->postJson(aiGradingPath($assignment, 'retry'))->assertForbidden();
});

test('cancelling an in-flight provider request records an unknown outcome and fences the job', function () {
    config()->set('services.gemini.key', 'test-key');
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty);
    Http::fake(function () use ($assignment) {
        $this->postJson(aiGradingPath($assignment, 'cancel'))->assertOk()
            ->assertJsonPath('data.error_code', 'outcome_unknown');
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'score' => 18, 'overall_comment' => 'Good.',
            'criterion_feedback' => [['criterion' => 'Accuracy', 'score' => 18, 'max' => 20, 'feedback' => 'Good.']],
        ])]]]]]]);
    });
    $this->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    runOneAiGradingJob();
    expect(AiFeedback::count())->toBe(0)
        ->and(AiProcessingRun::sole()->gradingSubmissions()->sole()->error_code)->toBe('outcome_unknown');
    Http::assertSentCount(1);
});

test('PostgreSQL rolls back a batch and its database queue entry together', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires an isolated PostgreSQL test database.');
    }
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    try {
        DB::transaction(function () use ($faculty, $assignment) {
            app(AiProcessingRunService::class)->startQueuedGradingBatch($faculty, $assignment);
            throw new RuntimeException('rollback test');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback test');
    }
    expect(AiProcessingRun::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
});
