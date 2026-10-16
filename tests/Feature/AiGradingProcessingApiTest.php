<?php

use App\Models\AiFeedback;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

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

test('owning faculty and administrators can start batch grading while students and other faculty are denied', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $student = User::factory()->create(['role' => 'student']);
    $path = aiGradingPath($assignment, 'start');

    $this->actingAs($faculty)->postJson($path, ['mode' => 'batch'])
        ->assertOk()->assertJsonPath('data.total_submissions', 2);
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
    $response->assertJsonStructure(['success', 'message', 'data' => [
        'status', 'total_submissions', 'pending_count', 'processing_count', 'completed_count',
        'failed_count', 'progress_percentage', 'next_eligible_at', 'can_continue', 'can_retry',
        'can_cancel', 'error_code', 'error_message',
    ]]);
    expect($response->json('data'))->not->toHaveKeys(['id', 'lease_token', 'content']);
});

test('invalid encrypted assignment identifiers return not found', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)->getJson('/assignments/invalid/ai-grading/status')->assertNotFound();
});

test('process next grades exactly one item and never mutates final grades', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture();
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
    fakeSuccessfulAiGrade();

    $response = $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'next'));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.outcome', 'completed')
        ->assertJsonPath('data.completed_count', 1)
        ->assertJsonPath('data.pending_count', 1);
    expect(AiFeedback::query()->count())->toBe(1)
        ->and(Grade::query()->count())->toBe(0);
    Http::assertSentCount(1);
});

test('provider 503 and 429 responses are deferred without false success', function (int $status, string $code) {
    config()->set('services.gemini.key', 'test-key');
    config()->set('ai_processing.grading_requests_per_minute', 20);
    Http::fake(['*' => Http::response([], $status)]);
    ['faculty' => $faculty, 'assignment' => $assignment] = createAiGradingApiFixture(1);
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();

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
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();

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
    $this->actingAs($faculty)->postJson(aiGradingPath($assignment, 'start'), ['mode' => 'batch'])->assertOk();
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
        ->assertOk()
        ->assertJsonPath('data.total_submissions', 0)
        ->assertJsonPath('data.status', 'completed');
    expect(AiFeedback::query()->count())->toBe(0);
});
