<?php

use App\Jobs\CheckPlagiarismJob;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\PlagiarismReport;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Services\AIGraderService;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;

function createPlagiarismApiFixture(int $submissionCount = 2): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create([
        'name' => 'Summer Semester',
        'school_year' => '2026-2027',
        'start_date' => '2027-06-01',
        'end_date' => '2027-07-31',
        'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'API301', 'name' => 'Processing API']);
    $section = Section::create([
        'name' => 'BSCS 3C',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'title' => 'API Pairwise Assignment',
        'instructions' => 'Submit a response.',
        'type' => 'essay',
        'max_score' => 20,
        'is_published' => true,
    ]);

    foreach ($submissionCount > 0 ? range(1, $submissionCount) : [] as $number) {
        $student = Student::create([
            'student_no' => 'API-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'first_name' => 'API',
            'last_name' => "Student {$number}",
            'email' => "processing-api-{$number}@example.test",
            'course' => 'BSCS',
            'year_level' => 3,
        ]);
        Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'content' => "API processing content {$number}",
        ]);
    }

    return compact('faculty', 'assignment');
}

function plagiarismAssignmentPath(Assignment $assignment, string $suffix): string
{
    return '/assignments/'.Crypt::encryptString((string) $assignment->id).'/plagiarism/'.$suffix;
}

function bindProcessingApiAi(): void
{
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('modelUsed')->andReturn('api-test-model');
    $ai->shouldReceive('estimatePlagiarismPairInputTokens')->andReturn(100);
    $ai->shouldReceive('analyzePlagiarismPair')->andReturn([
        'similarity' => 15,
        'flagged' => false,
        'explanation' => 'No meaningful overlap found.',
    ]);
    $ai->shouldReceive('validatePlagiarismPairResponse')->andReturn([
        'similarity' => 15,
        'flagged' => false,
        'explanation' => 'No meaningful overlap found.',
    ]);
    app()->instance(AIGraderService::class, $ai);
}

function bindUnavailableProcessingApiAi(): void
{
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('modelUsed')->andReturn('api-test-model');
    $ai->shouldReceive('estimatePlagiarismPairInputTokens')->andReturn(100);
    $ai->shouldReceive('analyzePlagiarismPair')->andReturn([
        'error' => 'Gemini could not process the request. Please try again later.',
        'error_code' => 'provider_unavailable',
    ]);
    app()->instance(AIGraderService::class, $ai);
}

test('faculty can start and retrieve a safe plagiarism processing status', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $path = plagiarismAssignmentPath($assignment, 'processing/start');

    $this->actingAs($faculty)->postJson($path)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_pairs', 1)
        ->assertJsonPath('data.completed_pairs', 0)
        ->assertJsonMissingPath('data.content');

    AiProcessingRun::query()->firstOrFail()
        ->plagiarismPairs()
        ->firstOrFail()
        ->update(['next_eligible_at' => now()->addMinute()]);

    $response = $this->actingAs($faculty)->getJson(plagiarismAssignmentPath($assignment, 'processing/status'));

    $response
        ->assertOk()
        ->assertJsonPath('data.remaining_pairs', 1)
        ->assertJsonPath('data.progress_percentage', 0)
        ->assertJsonPath('data.can_continue', false);
    expect($response->json('data.next_eligible_at'))->toContain('T');
});

test('the plagiarism route resolves the existing report page and protects report data', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $path = '/assignments/'.Crypt::encryptString((string) $assignment->id).'/plagiarism';

    $this->actingAs($faculty)->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('assignments/PlagiarismReport')
            ->has('assignmentId'),
        );
    $this->actingAs($otherFaculty)->getJson($path.'/data')->assertForbidden();
});

test('duplicate starts reuse the active processing run', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $path = plagiarismAssignmentPath($assignment, 'processing/start');

    $this->actingAs($faculty)->postJson($path)->assertOk();
    $this->actingAs($faculty)->postJson($path)->assertOk();

    expect($assignment->fresh()->submissions()->count())->toBe(2)
        ->and(AiProcessingRun::query()->count())->toBe(1);
});

test('faculty can start a fresh run after cancellation without replacing reports or submissions', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $studentIds = $assignment->submissions()->orderBy('id')->pluck('student_id');
    PlagiarismReport::create([
        'assignment_id' => $assignment->id,
        'student_a_id' => $studentIds->first(),
        'student_b_id' => $studentIds->last(),
        'similarity_score' => 25,
        'flagged' => false,
        'explanation' => 'Historical published report.',
    ]);
    $startPath = plagiarismAssignmentPath($assignment, 'processing/start');

    $this->actingAs($faculty)->postJson($startPath)->assertOk();
    $cancelledRun = AiProcessingRun::query()->sole();

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/cancel'))
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'cancelled');

    $this->actingAs($faculty)->postJson($startPath)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_pairs', 1);

    $freshRun = AiProcessingRun::query()->latest('id')->firstOrFail();
    expect($freshRun->id)->not->toBe($cancelledRun->id)
        ->and($cancelledRun->fresh()->status)->toBe('failed')
        ->and($cancelledRun->fresh()->error_code)->toBe('cancelled')
        ->and($assignment->fresh()->submissions()->count())->toBe(2)
        ->and($assignment->fresh()->plagiarismReports()->count())->toBe(1);

    $this->actingAs($faculty)->postJson($startPath)->assertOk();

    expect(AiProcessingRun::query()->count())->toBe(2);
});

test('cross faculty and students cannot access processing endpoints', function () {
    ['assignment' => $assignment] = createPlagiarismApiFixture();
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $path = plagiarismAssignmentPath($assignment, 'processing/start');

    $this->actingAs($otherFaculty)->postJson($path)->assertForbidden();
    $this->actingAs($studentUser)->postJson($path)->assertForbidden();
    $this->actingAs($studentUser)
        ->getJson('/assignments/'.Crypt::encryptString((string) $assignment->id).'/plagiarism/data')
        ->assertForbidden();
});

test('administrators can start a plagiarism processing run', function () {
    ['assignment' => $assignment] = createPlagiarismApiFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->get('/assignments/'.Crypt::encryptString((string) $assignment->id).'/plagiarism')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('assignments/PlagiarismReport')
            ->has('assignmentId'),
        );
});

test('invalid encrypted identifiers return not found', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)
        ->getJson('/assignments/not-an-encrypted-id/plagiarism/processing/status')
        ->assertNotFound();
});

test('process endpoint handles only one staged pair and cancellation invalidates the run', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture(3);
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))->assertOk();
    bindProcessingApiAi();

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))
        ->assertOk()
        ->assertJsonPath('data.completed_pairs', 1)
        ->assertJsonPath('data.remaining_pairs', 2);

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/cancel'))
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'cancelled');

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))
        ->assertStatus(422);
});

test('provider unavailability defers a comparison without reporting false success and allows a later retry', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))->assertOk();
    bindUnavailableProcessingApiAi();

    $response = $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'));

    $response
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.outcome', 'deferred')
        ->assertJsonPath('data.completed_pairs', 0)
        ->assertJsonPath('data.remaining_pairs', 1)
        ->assertJsonPath('data.error_code', 'provider_unavailable')
        ->assertJsonPath('data.can_continue', false);
    expect($response->json('data.next_eligible_at'))->toContain('T');

    $pair = AiProcessingRun::query()->sole()->plagiarismPairs()->sole();
    expect($pair->status)->toBe('pending')
        ->and($pair->error_code)->toBe('provider_unavailable')
        ->and($pair->attempt_count)->toBe(1);

    $pair->update(['next_eligible_at' => now()->subSecond()]);
    bindProcessingApiAi();

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/retry'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.outcome', 'completed')
        ->assertJsonPath('data.completed_pairs', 1)
        ->assertJsonPath('data.remaining_pairs', 0);
});

test('a terminal provider failure preserves the failed run and permits a fresh authorized analysis', function () {
    config()->set('ai_processing.plagiarism_max_pair_attempts', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $studentIds = $assignment->submissions()->orderBy('id')->pluck('student_id');
    PlagiarismReport::create([
        'assignment_id' => $assignment->id,
        'student_a_id' => $studentIds->first(),
        'student_b_id' => $studentIds->last(),
        'similarity_score' => 20,
        'flagged' => false,
        'explanation' => 'Historical report.',
    ]);
    $startPath = plagiarismAssignmentPath($assignment, 'processing/start');
    $this->actingAs($faculty)->postJson($startPath)->assertOk();
    bindUnavailableProcessingApiAi();

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.outcome', 'failed')
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failed_pairs', 1)
        ->assertJsonPath('data.error_code', 'provider_unavailable');

    $failedRun = AiProcessingRun::query()->sole();
    expect($failedRun->plagiarismSubmissions()->count())->toBe(2)
        ->and($assignment->fresh()->submissions()->count())->toBe(2)
        ->and($assignment->fresh()->plagiarismReports()->count())->toBe(1);

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/retry'))
        ->assertStatus(422);
    $this->actingAs($faculty)->postJson($startPath)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_pairs', 1);

    expect(AiProcessingRun::query()->count())->toBe(2)
        ->and($failedRun->fresh()->status)->toBe('failed')
        ->and($assignment->fresh()->plagiarismReports()->count())->toBe(1);
});

test('administrators can start a fresh analysis after a faculty provider failure', function () {
    config()->set('ai_processing.plagiarism_max_pair_attempts', 1);
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $startPath = plagiarismAssignmentPath($assignment, 'processing/start');
    $this->actingAs($faculty)->postJson($startPath)->assertOk();
    bindUnavailableProcessingApiAi();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))
        ->assertJsonPath('data.status', 'failed');

    $this->actingAs($admin)->postJson($startPath)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
    expect(AiProcessingRun::query()->count())->toBe(2);
});

test('the retired legacy plagiarism dispatch route is unavailable', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();

    $this->actingAs($faculty)->postJson('/assignments/'.Crypt::encryptString((string) $assignment->id).'/plagiarism/run')
        ->assertNotFound();
});

test('legacy jobs do not run when pairwise processing state exists', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))->assertOk();
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldNotReceive('checkPlagiarism');

    (new CheckPlagiarismJob($assignment))->handle($ai);
});

test('publishes a fully completed run atomically and idempotently', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))->assertOk();
    bindProcessingApiAi();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))->assertOk();

    $response = $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($response->json('data.published_at'))->not->toBeNull();

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($assignment->plagiarismReports()->count())->toBe(1)
        ->and($assignment->fresh()->plagiarism_published_run_id)->toBe(AiProcessingRun::query()->value('id'));

    $this->actingAs($faculty)->getJson(plagiarismAssignmentPath($assignment, 'data'))
        ->assertOk()
        ->assertJsonPath('data.reports.0.flagged', false)
        ->assertJsonPath('data.reports.0.explanation', 'No meaningful overlap found.');
});

test('preserves existing reports when a run is incomplete or submissions changed', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $studentIds = $assignment->submissions()->orderBy('id')->pluck('student_id');
    PlagiarismReport::create([
        'assignment_id' => $assignment->id,
        'student_a_id' => $studentIds[0],
        'student_b_id' => $studentIds[1],
        'similarity_score' => 20,
        'flagged' => false,
        'explanation' => 'Existing report.',
    ]);
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))->assertOk();

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'))
        ->assertStatus(422);
    expect($assignment->plagiarismReports()->count())->toBe(1);

    bindProcessingApiAi();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))->assertOk();
    $assignment->submissions()->first()->update(['content' => 'Changed after snapshot']);

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'))
        ->assertStatus(422);
    expect($assignment->plagiarismReports()->count())->toBe(1);
});

test('does not publish zero comparison runs', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture(0);

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))
        ->assertOk()
        ->assertJsonPath('data.total_pairs', 0);

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'))
        ->assertStatus(422);
});

test('publishing rejects cross-faculty access and an active lease', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createPlagiarismApiFixture();
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/start'))->assertOk();
    bindProcessingApiAi();
    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/next'))->assertOk();

    $this->actingAs($otherFaculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'))
        ->assertForbidden();

    AiProcessingRun::query()->firstOrFail()->update([
        'lease_token' => 'active-test-lease',
        'lease_expires_at' => now()->addMinute(),
    ]);

    $this->actingAs($faculty)->postJson(plagiarismAssignmentPath($assignment, 'processing/publish'))
        ->assertStatus(422);
    expect($assignment->plagiarismReports()->count())->toBe(0);
});
