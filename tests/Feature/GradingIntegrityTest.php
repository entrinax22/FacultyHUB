<?php

use App\Jobs\GradeCodeJob;
use App\Jobs\GradeEssayJob;
use App\Models\AiFeedback;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Services\AIGraderService;
use Illuminate\Support\Facades\Crypt;

function createGradingIntegrityFixture(): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create([
        'user_id' => $studentUser->id,
        'student_no' => '2026-90001',
        'first_name' => 'Grading',
        'last_name' => 'Student',
        'email' => $studentUser->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    $semester = Semester::create([
        'name' => 'First Semester',
        'school_year' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'CS901', 'name' => 'Secure Grading']);
    $section = Section::create([
        'name' => 'BSCS 9A',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    Enrollment::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'semester_id' => $semester->id,
        'status' => 'active',
    ]);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'title' => 'Secure Essay',
        'instructions' => 'Write a short essay.',
        'type' => 'essay',
        'max_score' => 20,
        'is_published' => true,
    ]);
    $submission = Submission::create([
        'assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'content' => 'The original student submission must remain unchanged.',
        'status' => 'grading',
    ]);

    return compact('faculty', 'studentUser', 'student', 'semester', 'subject', 'section', 'assignment', 'submission');
}

function validEssayAiResponse(): array
{
    return [
        'score' => 18.5,
        'overall_comment' => 'A clear and well supported response.',
        'criterion_feedback' => [[
            'criterion' => 'Clarity',
            'score' => 18.5,
            'max' => 20,
            'feedback' => 'The reasoning is clear.',
        ]],
    ];
}

test('AI grading accepts valid rubric feedback', function () {
    $response = validEssayAiResponse();

    expect(app(AIGraderService::class)->validateGradeResponse($response, 20, 'essay'))
        ->toBe($response);
});

test('AI grading rejects malformed or invalid scores', function (array $response) {
    expect(fn () => app(AIGraderService::class)->validateGradeResponse($response, 20, 'essay'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'missing score' => fn () => array_diff_key(validEssayAiResponse(), ['score' => true]),
    'negative score' => fn () => array_replace(validEssayAiResponse(), ['score' => -1]),
    'score above maximum' => fn () => array_replace(validEssayAiResponse(), ['score' => 21]),
    'numeric string score' => fn () => array_replace(validEssayAiResponse(), ['score' => '18']),
    'invalid criterion feedback' => fn () => array_replace(validEssayAiResponse(), ['criterion_feedback' => 'invalid']),
]);

test('AI code grading rejects invalid feedback structures', function () {
    $response = [
        'score' => 15,
        'overall_comment' => 'Works well.',
        'correctness' => 100,
        'logic_quality' => 90,
        'code_quality' => 80,
        'inline_comments' => [['line' => 0, 'comment' => 'Invalid line number.']],
    ];

    expect(fn () => app(AIGraderService::class)->validateGradeResponse($response, 20, 'code'))
        ->toThrow(InvalidArgumentException::class);
});

test('valid AI feedback is persisted as a reviewable grade suggestion', function () {
    ['submission' => $submission] = createGradingIntegrityFixture();
    $response = validEssayAiResponse();
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('gradeEssay')->once()->andReturn($response);
    $ai->shouldReceive('validateGradeResponse')->once()->with($response, 20.0, 'essay')->andReturn($response);
    $ai->shouldReceive('modelUsed')->once()->andReturn('test-model');

    (new GradeEssayJob($submission))->handle($ai);

    $this->assertDatabaseHas('ai_feedback', [
        'submission_id' => $submission->id,
        'score' => 18.5,
        'model_used' => 'test-model',
    ]);
    expect($submission->fresh()->status)->toBe('graded');
    expect($submission->grade)->toBeNull();
});

test('valid AI code feedback remains a suggestion until faculty approval', function () {
    ['assignment' => $assignment, 'submission' => $submission] = createGradingIntegrityFixture();
    $assignment->update(['type' => 'code', 'language' => 'php']);
    $response = [
        'score' => 18,
        'overall_comment' => 'The solution meets the requirements.',
        'correctness' => 90,
        'logic_quality' => 90,
        'code_quality' => 85,
        'inline_comments' => [],
    ];
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('gradeCode')->once()->andReturn($response);
    $ai->shouldReceive('validateGradeResponse')->once()->with($response, 20.0, 'code')->andReturn($response);
    $ai->shouldReceive('modelUsed')->once()->andReturn('test-model');

    (new GradeCodeJob($submission))->handle($ai);

    expect($submission->fresh()->status)->toBe('graded');
    expect($submission->fresh()->grade)->toBeNull();
    $this->assertDatabaseHas('ai_feedback', [
        'submission_id' => $submission->id,
        'score' => 18,
    ]);
});

test('AI provider failures preserve the submission content and create no feedback', function () {
    ['submission' => $submission] = createGradingIntegrityFixture();
    $ai = Mockery::mock(AIGraderService::class);
    $ai->shouldReceive('gradeEssay')->once()->andReturn(['error' => 'provider unavailable']);

    expect(fn () => (new GradeEssayJob($submission))->handle($ai))
        ->toThrow(RuntimeException::class);

    expect($submission->fresh()->content)->toBe('The original student submission must remain unchanged.')
        ->and(AiFeedback::query()->where('submission_id', $submission->id)->exists())->toBeFalse();
});

test('faculty cannot access or modify another faculty members grading resources', function () {
    ['assignment' => $assignment, 'submission' => $submission] = createGradingIntegrityFixture();
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $submissionId = Crypt::encryptString((string) $submission->id);

    $this->actingAs($otherFaculty)
        ->getJson(route('submissions.grade.data', $submissionId))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->postJson(route('submissions.approve', $submissionId), ['raw_score' => 18])
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->postJson(route('grades.release'), [
            'assignment_id' => Crypt::encryptString((string) $assignment->id),
        ])
        ->assertForbidden();
});

test('faculty approval enforces the assignment maximum and keeps grades unreleased', function () {
    ['faculty' => $faculty, 'submission' => $submission] = createGradingIntegrityFixture();
    $submissionId = Crypt::encryptString((string) $submission->id);

    $this->actingAs($faculty)
        ->postJson(route('submissions.approve', $submissionId), ['raw_score' => 21])
        ->assertStatus(422);

    $this->assertDatabaseMissing('grades', ['submission_id' => $submission->id]);

    $this->postJson(route('submissions.approve', $submissionId), ['raw_score' => 18])
        ->assertOk()
        ->assertJsonPath('data.grade.is_released', false);
});

test('class record scores require faculty ownership and an active enrollment', function () {
    ['faculty' => $faculty, 'semester' => $semester, 'section' => $section] = createGradingIntegrityFixture();
    $component = GradingComponent::create([
        'section_id' => $section->id,
        'name' => 'Activities',
        'weight_percentage' => 100,
        'max_score' => 20,
        'order' => 1,
    ]);
    $item = GradingItem::create([
        'section_id' => $section->id,
        'component_id' => $component->id,
        'name' => 'Activity 1',
        'max_score' => 20,
        'order' => 1,
    ]);
    $outsiderUser = User::factory()->create(['role' => 'student']);
    $outsider = Student::create([
        'user_id' => $outsiderUser->id,
        'student_no' => '2026-90002',
        'first_name' => 'Outside',
        'last_name' => 'Student',
        'email' => $outsiderUser->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    $payload = [
        'student_id' => Crypt::encryptString((string) $outsider->id),
        'section_id' => Crypt::encryptString((string) $section->id),
        'item_id' => Crypt::encryptString((string) $item->id),
        'score' => 15,
    ];

    $this->actingAs($faculty)
        ->putJson(route('class-record.item-scores.update'), $payload)
        ->assertNotFound();

    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $this->actingAs($otherFaculty)
        ->putJson(route('class-record.item-scores.update'), $payload)
        ->assertForbidden();
});
