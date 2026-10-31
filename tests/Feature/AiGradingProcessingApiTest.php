<?php

use App\Models\AiFeedback;
use App\Models\AiGradingRunSubmission;
use App\Models\AiProcessingRun;
use App\Models\AiSetting;
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

test('grading run recovery continues after the historical run retry count reaches its maximum', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(39);
    $service = app(AiProcessingRunService::class);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = $service->prepareGradingRun($faculty, $assignment);
    $run->update(['retry_count' => $run->max_retry_attempts]);

    runOneAiGradingJob();

    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.total_submissions', 39)
        ->assertJsonPath('data.completed_count', 1)->assertJsonPath('data.pending_count', 38)
        ->assertJsonPath('data.failed_count', 0);
    expect($run->fresh()->retry_count)->toBe(3);
    expect($run->fresh()->queue_job_id)->not->toBeNull();
    Http::assertSentCount(1);
});

test('coordinator exception retries stop after the bounded per-job attempts without a run retry cap', function () {
    Http::fake([]);
    Http::preventStrayRequests();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(39);
    $service = app(AiProcessingRunService::class);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = $service->prepareGradingRun($faculty, $assignment);
    $run->update(['status' => 'processing', 'lease_token' => 'expired', 'lease_expires_at' => now()->subSecond(), 'max_retry_attempts' => 2]);
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('toPlainText')->andReturn('text');
    $ai->shouldReceive('modelUsed')->andThrow(new RuntimeException('simulated pre-request failure'));
    app()->instance(AIGraderService::class, $ai);

    runOneAiGradingJob();
    $run->refresh();
    expect($run->retry_count)->toBe(1)->and($run->queue_generation)->toBe(2);
    $this->travelTo($run->lease_expires_at->copy()->addSeconds(2));
    runOneAiGradingJob();

    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'processing')->assertJsonPath('data.pending_count', 38)
        ->assertJsonPath('data.processing_count', 1)->assertJsonPath('data.completed_count', 0)
        ->assertJsonPath('data.failed_count', 0)
        ->assertJsonPath('data.error_code', 'queue_failed');
    expect($run->fresh()->retry_count)->toBe(2)->and($run->gradingSubmissions()->sum('attempt_count'))->toBe(0)
        ->and($run->fresh()->queue_job_id)->toBeNull();
    Http::assertNothingSent();
});

test('global expired-run recovery preserves grading work beyond the historical retry maximum', function () {
    Http::fake([]);
    Http::preventStrayRequests();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $run->update(['status' => 'processing', 'lease_token' => 'expired', 'lease_expires_at' => now()->subSecond(), 'retry_count' => $run->max_retry_attempts]);

    expect($service->recoverExpiredRuns())->toBe(1);
    $this->actingAs($faculty)->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.error_code', null)
        ->assertJsonPath('data.pending_count', 2)->assertJsonPath('data.failed_count', 0);
    expect($run->fresh()->retry_count)->toBe($run->max_retry_attempts + 1);
    Http::assertNothingSent();
});

test('failed submission summaries are authorized paginated and contain only safe review details', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(11);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $run->gradingSubmissions()->update(['status' => 'failed', 'error_code' => 'invalid_response']);
    $first = $run->gradingSubmissions()->orderBy('id')->firstOrFail();
    $first->update(['error_code' => 'outcome_unknown', 'content' => 'private submission content']);
    $run->gradingSubmissions()->orderBy('id')->skip(1)->firstOrFail()->update(['error_code' => 'unexpected private provider error']);
    $run->gradingSubmissions()->orderByDesc('id')->firstOrFail()->submission->delete();
    $run->update(['status' => 'failed', 'error_code' => 'outcome_unknown']);

    $response = $this->actingAs($faculty)->getJson(aiGradingPath($assignment, 'status'))
        ->assertOk()->assertJsonPath('data.failed_count', 11)
        ->assertJsonPath('data.failed_submissions.total', 11)
        ->assertJsonPath('data.failed_submissions.last_page', 2)
        ->assertJsonPath('data.retryable_count', 10)
        ->assertJsonPath('data.failed_submissions.items.0.status', 'outcome_unknown')
        ->assertJsonPath('data.failed_submissions.items.0.student_name', 'API Student 1')
        ->assertJsonPath('data.failed_submissions.items.0.retryable', true)
        ->assertJsonPath('data.failed_submissions.items.1.retryable', false);
    expect($response->json('data.failed_submissions.items'))->toHaveCount(10)
        ->and($response->json('data.failed_submissions.items.0.last_updated_at'))->not->toBeNull()
        ->and(Crypt::decryptString($response->json('data.failed_submissions.items.0.submission_id')))->toBe((string) $first->submission_id)
        ->and($response->getContent())->not->toContain('private submission content', 'unexpected private provider error');
    $this->getJson(aiGradingPath($assignment, 'status').'?failed_page=2')->assertOk()
        ->assertJsonCount(1, 'data.failed_submissions.items')->assertJsonPath('data.failed_submissions.page', 2)
        ->assertJsonPath('data.failed_submissions.items.0.student_name', 'Student unavailable')
        ->assertJsonPath('data.failed_submissions.items.0.submission_id', null);
    $this->getJson(aiGradingPath($assignment, 'status').'?failed_page=0')->assertUnprocessable();
    $run->update(['retry_count' => $run->max_retry_attempts]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.retryable_count', 10)->assertJsonPath('data.can_retry', true);
    $this->actingAs(User::factory()->create(['role' => 'faculty']))->getJson(aiGradingPath($assignment, 'status'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'student']))->getJson(aiGradingPath($assignment, 'status'))->assertForbidden();
    Http::assertNothingSent();
});

test('unknown outcomes require explicit confirmation even for individual runs', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), individualGradingPayload($assignment))->assertOk();
    $run = AiProcessingRun::sole();
    $run->gradingSubmissions()->update(['status' => 'failed', 'error_code' => 'outcome_unknown']);
    $run->update([
        'status' => 'failed',
        'error_code' => 'outcome_unknown',
        'retry_count' => $run->max_retry_attempts,
    ]);
    $this->postJson(aiGradingPath($assignment, 'retry'))->assertUnprocessable()->assertJsonValidationErrors('confirm_unknown');
    $this->postJson(aiGradingPath($assignment, 'retry'), ['confirm_unknown' => true])->assertOk();
    expect($run->fresh()->gradingSubmissions()->sole()->error_code)->toBeNull();
    Http::assertNothingSent();
});

test('faculty can manually retry eligible failures after more than three historical retries', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = $service->prepareGradingRun($faculty, $assignment);
    $work = $run->gradingSubmissions()->sole();
    $work->update(['status' => 'failed', 'error_code' => 'provider_unavailable', 'attempt_count' => 5]);
    $run->update(['status' => 'failed', 'error_code' => 'retry_limit_reached', 'retry_count' => 12]);

    $this->postJson(aiGradingPath($assignment, 'retry'))->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect($run->fresh()->retry_count)->toBe(13)
        ->and($work->fresh()->status)->toBe('pending')
        ->and($work->fresh()->attempt_count)->toBe(0)
        ->and($work->fresh()->error_code)->toBeNull()
        ->and(DB::table('jobs')->where('id', $run->fresh()->queue_job_id)->exists())->toBeTrue();
    Http::assertNothingSent();
});

test('historical failed runs do not restart from stale coordinator delivery', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $run->update(['status' => 'failed', 'error_code' => 'retry_limit_reached']);
    $jobCount = DB::table('jobs')->where('queue', 'ai-grading')->count();

    (new ProcessAiGradingRun($run->id, $run->queue_generation))
        ->handle($service, app(AIGraderService::class));

    expect($run->fresh()->status)->toBe('failed')
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe($jobCount);
    Http::assertNothingSent();
});

test('a failed run resumes its existing pending snapshots after explicit unknown-outcome confirmation', function () {
    fakeSuccessfulAiGrade();
    Http::preventStrayRequests();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(39);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $work = $run->gradingSubmissions()->orderBy('id')->firstOrFail();
    $work->update(['error_code' => 'outcome_unknown', 'attempt_count' => 0]);
    $run->update([
        'status' => 'failed',
        'error_code' => 'retry_limit_reached',
        'retry_count' => 12,
        'queue_generation' => 4,
        'queue_job_id' => null,
        'active_key' => null,
    ]);
    DB::table('jobs')->where('queue', 'ai-grading')->delete();

    $this->actingAs($faculty)->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.can_recover_failed_run', true)
        ->assertJsonPath('data.recoverable_pending_count', 39)
        ->assertJsonPath('data.pending_unknown_count', 1);
    $this->postJson(aiGradingPath($assignment, 'recover'))->assertUnprocessable()
        ->assertJsonPath('message', 'Confirm that you reviewed pending submissions with an unknown provider outcome before resuming.');
    expect($run->fresh()->status)->toBe('failed')
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);

    $this->postJson(aiGradingPath($assignment, 'recover'), ['confirm_unknown' => true])->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.pending_count', 39)
        ->assertJsonPath('data.pending_unknown_count', 0);
    expect($run->fresh()->id)->toBe($run->id)
        ->and($run->fresh()->queue_generation)->toBe(5)
        ->and($run->gradingSubmissions()->count())->toBe(39)
        ->and($work->fresh()->status)->toBe('pending')
        ->and($work->fresh()->attempt_count)->toBe(0)
        ->and($work->fresh()->error_code)->toBeNull()
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);

    $this->postJson(aiGradingPath($assignment, 'recover'), ['confirm_unknown' => true])->assertOk();
    expect($run->fresh()->queue_generation)->toBe(5)
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);

    runOneAiGradingJob();
    expect($work->fresh()->status)->toBe('completed')
        ->and($run->fresh()->gradingSubmissions()->count())->toBe(39)
        ->and($run->fresh()->completed_work_units)->toBe(1);
    Http::assertSentCount(1);
});

test('failed-run recovery preserves completed results and protects approved grades', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $works = $run->gradingSubmissions()->orderBy('id')->get();
    $works[0]->update(['status' => 'completed', 'result' => ['score' => 18]]);
    $works[0]->submission->update(['status' => 'graded']);
    $approvedSubmission = $works[1]->submission;
    $approvedSubmission->update(['status' => 'approved']);
    Grade::create([
        'submission_id' => $approvedSubmission->id,
        'student_id' => $approvedSubmission->student_id,
        'section_id' => $assignment->section_id,
        'assignment_id' => $assignment->id,
        'raw_score' => 17,
        'max_score' => 20,
    ]);
    $run->update(['status' => 'failed', 'error_code' => 'retry_limit_reached', 'active_key' => null, 'queue_job_id' => null]);
    DB::table('jobs')->where('queue', 'ai-grading')->delete();

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'recover'))->assertOk();
    runOneAiGradingJob();

    expect($works[0]->fresh()->status)->toBe('completed')
        ->and($approvedSubmission->fresh()->status)->toBe('approved')
        ->and($approvedSubmission->grade()->value('raw_score'))->toEqual(17)
        ->and($works[1]->fresh()->error_code)->toBe('grade_protected')
        ->and($run->fresh()->status)->toBe('failed')
        ->and(AiFeedback::count())->toBe(0);
    Http::assertNothingSent();
});

test('cancelled runs cannot use failed-run recovery', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    $run->update(['status' => 'failed', 'error_code' => 'cancelled', 'active_key' => null, 'queue_job_id' => null]);
    DB::table('jobs')->where('queue', 'ai-grading')->delete();

    $this->actingAs($faculty)->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.can_recover_failed_run', false);
    $this->postJson(aiGradingPath($assignment, 'recover'))->assertUnprocessable()
        ->assertJsonPath('message', 'This failed grading run is not eligible for recovery.');
    expect($run->fresh()->status)->toBe('failed')->and(DB::table('jobs')->count())->toBe(0);
    Http::assertNothingSent();
});

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
    AiSetting::current()->update(['grading_daily_input_token_budget' => 1]);
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
        ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.completed_count', 2)
        ->assertJsonPath('data.monitoring_state', 'completed');
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
        ->assertJsonPath('data.outcome_unknown_count', 1)->assertJsonPath('data.can_retry', true)
        ->assertJsonPath('data.monitoring_state', 'outcome_unknown');
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

test('monitoring distinguishes delayed jobs active leases and stalled queue entries without changing them', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk()
        ->assertJsonPath('data.monitoring_state', 'preparing');
    $run = AiProcessingRun::sole();
    $jobId = $run->queue_job_id;
    $run->update(['preparation_status' => 'prepared', 'error_code' => 'provider_rate_limited']);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'queued');
    DB::table('jobs')->where('id', $jobId)->update(['available_at' => now()->addMinute()->timestamp]);
    $response = $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.monitoring_state', 'waiting_provider')->assertJsonPath('data.can_resume', false);
    expect($response->json('data.next_attempt_at'))->not->toBeNull()
        ->and($response->json('data.last_activity_at'))->not->toBeNull();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'waiting_provider');
    expect(DB::table('jobs')->where('id', $jobId)->count())->toBe(1)->and($run->fresh()->queue_generation)->toBe(1);

    $run->update(['status' => 'processing', 'lease_token' => 'expired', 'lease_expires_at' => now()->subSecond()]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'waiting_provider')->assertJsonPath('data.can_resume', false);

    $run->update(['status' => 'processing', 'lease_token' => 'active', 'lease_expires_at' => now()->addMinute(), 'error_code' => null]);
    DB::table('jobs')->where('id', $jobId)->update(['available_at' => now()->subMinute()->timestamp, 'reserved_at' => now()->timestamp]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'processing')->assertJsonPath('data.can_resume', false);
    $run->update(['lease_expires_at' => now()->subSecond()]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'stalled')->assertJsonPath('data.can_resume', false);
    DB::table('jobs')->where('id', $jobId)->delete();
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'stalled')->assertJsonPath('data.can_resume', true);
    Http::assertNothingSent();
});

test('monitoring marks overdue ready and expired reserved jobs as potentially stalled', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = AiProcessingRun::sole();
    DB::table('jobs')->where('id', $run->queue_job_id)->update(['available_at' => now()->subMinutes(5)->timestamp]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'stalled')->assertJsonPath('data.can_resume', false);
    DB::table('jobs')->where('id', $run->queue_job_id)->update(['reserved_at' => now()->subMinutes(5)->timestamp]);
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.monitoring_state', 'stalled')->assertJsonPath('data.can_resume', false);
    Http::assertNothingSent();
});

test('laravel database queue release replaces the reserved job identifier', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $run = app(AiProcessingRunService::class)->startQueuedGradingBatch($faculty, $assignment);
    $job = \Illuminate\Support\Facades\Queue::connection('database')->pop('ai-grading');
    expect((int) $job->getJobId())->toBe($run->queue_job_id);
    $job->release(30);
    $replacement = DB::table('jobs')->where('queue', 'ai-grading')->sole();
    expect((int) $replacement->id)->not->toBe($run->queue_job_id)
        ->and((int) $replacement->available_at)->toBeGreaterThan(now()->timestamp);
});

test('an unexpected worker exception schedules a tracked fenced retry without a database job release', function () {
    Http::fake([]);
    Http::preventStrayRequests();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('toPlainText')->andReturn('text');
    $ai->shouldReceive('modelUsed')->andThrow(new RuntimeException('simulated worker failure'));
    app()->instance(AIGraderService::class, $ai);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $originalId = AiProcessingRun::sole()->queue_job_id;
    runOneAiGradingJob();

    $run = AiProcessingRun::sole();
    expect($run->status)->toBe('processing')
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1)
        ->and($run->queue_job_id)->not->toBe($originalId)
        ->and($run->queue_generation)->toBe(2)
        ->and(DB::table('jobs')->where('id', $run->queue_job_id)->exists())->toBeTrue();
    $response = $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.monitoring_state', 'processing')->assertJsonPath('data.can_resume', false);
    expect($response->json('data.next_attempt_at'))->not->toBeNull();
    Http::assertNothingSent();

    $this->travelTo($run->lease_expires_at->copy()->addSeconds(2));
    app()->forgetInstance(AIGraderService::class);
    fakeSuccessfulAiGrade();
    runOneAiGradingJob();
    expect($run->fresh()->error_code)->toBeNull();
    expect($run->fresh()->status)->toBe('completed')->and(AiFeedback::count())->toBe(1);
    Http::assertSentCount(1);
});

test('unexpected coordinator failures are bounded and remain manually recoverable', function () {
    Http::fake([]);
    Http::preventStrayRequests();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('toPlainText')->andReturn('text');
    $ai->shouldReceive('modelUsed')->andThrow(new RuntimeException('simulated worker failure'));
    app()->instance(AIGraderService::class, $ai);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    for ($attempt = 0; $attempt < 2; $attempt++) {
        runOneAiGradingJob();
        $run = AiProcessingRun::sole()->fresh();
        if ($attempt < 1) {
            expect(DB::table('jobs')->where('id', $run->queue_job_id)->count())->toBe(1);
            $this->travelTo($run->lease_expires_at->copy()->addSeconds(2));
        }
    }
    expect($run->error_code)->toBe('queue_failed')->and($run->queue_job_id)->toBeNull()
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
    $this->travelTo($run->lease_expires_at->copy()->addSeconds(2));
    $this->getJson(aiGradingPath($assignment, 'status'))->assertJsonPath('data.can_resume', true);
    Http::assertNothingSent();
    app()->forgetInstance(AIGraderService::class);
    fakeSuccessfulAiGrade();
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    runOneAiGradingJob();
    expect($run->fresh()->status)->toBe('completed');
});

test('a rescheduled interrupted in-flight request becomes unknown without another provider call', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    DB::table('jobs')->where('id', $run->queue_job_id)->delete();
    $run->update(['status' => 'processing', 'lease_token' => 'active', 'lease_expires_at' => now()->addMinute()]);
    $run->gradingSubmissions()->update(['status' => 'processing', 'error_code' => 'provider_in_flight', 'lease_token' => 'active', 'lease_expires_at' => $run->lease_expires_at]);
    $service->rescheduleUnexpectedGradingFailure($run->id, $run->queue_generation, 1);
    expect(DB::table('jobs')->where('id', $run->fresh()->queue_job_id)->exists())->toBeTrue();
    $this->travelTo($run->lease_expires_at->copy()->addSeconds(2));
    runOneAiGradingJob();
    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->error_code)->toBe('outcome_unknown')
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
    Http::assertNothingSent();
});

test('budget exhaustion pauses the queued batch without contacting the provider', function () {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_daily_input_token_budget', 1);
    AiSetting::current()->update(['grading_daily_input_token_budget' => 1]);
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $response = $this->getJson(aiGradingPath($assignment, 'status'))->assertOk()
        ->assertJsonPath('data.error_code', 'budget_exhausted')->assertJsonPath('data.can_resume', false)
        ->assertJsonPath('data.budget_reason', 'budget_daily_tokens')->assertJsonPath('data.monitoring_state', 'paused_budget');
    expect(\Carbon\Carbon::parse($response->json('data.budget_reset_at'))->equalTo(now('America/Los_Angeles')->addDay()->startOfDay()))->toBeTrue();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->value('available_at'))->toBeGreaterThan(now()->timestamp);
    Http::assertNothingSent();
});

test('minute budget schedules one fenced continuation at the next window', function () {
    $this->travelTo(now()->startOfMinute()->addSeconds(10));
    fakeSuccessfulAiGrade();
    config()->set('ai_processing.grading_requests_per_minute', 1);
    AiSetting::current()->update(['grading_requests_per_minute' => 1]);
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
        ->assertJsonPath('data.monitoring_state', 'waiting_minute')
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
    AiSetting::current()->update([
        'grading_daily_request_budget' => 1,
        'grading_auto_continue' => false,
    ]);
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
    AiSetting::current()->update(['grading_daily_request_budget' => 2]);
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    $this->postJson(aiGradingPath($assignment, 'next'))->assertOk();
    expect(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(1);
    runOneAiGradingJob();
    expect(AiProcessingRun::sole()->status)->toBe('completed');
    Http::assertSentCount(2);
});

test('queued grading reads administrator budget changes dynamically and respects disabled continuation', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $settings = AiSetting::current();

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $settings->update([
        'grading_daily_request_budget' => 1,
        'grading_auto_continue' => false,
    ]);
    runOneAiGradingJob();
    runOneAiGradingJob();

    $run = AiProcessingRun::sole()->fresh();
    expect($run->error_code)->toBe('budget_exhausted')
        ->and($run->queue_job_id)->toBeNull()
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0)
        ->and(DB::table('ai_processing_budget_windows')->where('category', 'grading')->where('window_type', 'daily')->sum('request_count'))->toBe(1);
    Http::assertSentCount(1);
});

test('reducing a grading budget below persisted usage defers further requests', function () {
    fakeSuccessfulAiGrade();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $settings = AiSetting::current();
    $settings->update(['grading_daily_request_budget' => 2]);

    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    runOneAiGradingJob();
    $settings->update(['grading_daily_request_budget' => 1]);
    runOneAiGradingJob();

    $run = AiProcessingRun::sole()->fresh();
    expect($run->error_code)->toBe('budget_exhausted')
        ->and($run->gradingSubmissions()->where('status', 'pending')->sole()->attempt_count)->toBe(0)
        ->and(DB::table('ai_processing_budget_windows')->where('category', 'grading')->where('window_type', 'daily')->sum('request_count'))->toBe(1);
    Http::assertSentCount(1);
});

test('budget reevaluation never restarts a historical failed run', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $service = app(AiProcessingRunService::class);
    $run = $service->startQueuedGradingBatch($faculty, $assignment);
    $service->prepareGradingRun($faculty, $assignment);
    DB::table('jobs')->where('id', $run->queue_job_id)->delete();
    $run->gradingSubmissions()->update([
        'error_code' => 'budget_daily_requests',
        'next_eligible_at' => now()->addDay(),
    ]);
    $run->update(['status' => 'failed', 'error_code' => 'retry_limit_reached', 'queue_job_id' => null]);

    expect($service->reevaluatePausedGradingRuns())->toBe(0)
        ->and($run->fresh()->status)->toBe('failed')
        ->and(DB::table('jobs')->where('queue', 'ai-grading')->count())->toBe(0);
});

test('coordinator requires explicit confirmation before processing pending unknown outcome evidence', function () {
    Http::fake();
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(2);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    $run = AiProcessingRun::sole();
    app(AiProcessingRunService::class)->prepareGradingRun($faculty, $assignment);
    $run->gradingSubmissions()->firstOrFail()->update(['error_code' => 'outcome_unknown']);

    runOneAiGradingJob();

    expect($run->fresh()->error_code)->toBe('outcome_unknown')
        ->and($run->fresh()->queue_job_id)->toBeNull();
    Http::assertNothingSent();
    $this->postJson(aiGradingPath($assignment, 'next'))->assertUnprocessable();
    $this->postJson(aiGradingPath($assignment, 'next'), ['confirm_unknown' => true])->assertOk();
    expect($run->gradingSubmissions()->where('error_code', 'outcome_unknown')->exists())->toBeFalse();
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
    $this->postJson(aiGradingPath($assignment, 'recover'))->assertForbidden();
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
