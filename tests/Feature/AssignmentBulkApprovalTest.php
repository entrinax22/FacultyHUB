<?php

use App\Models\AiFeedback;
use App\Models\AiGradingRunSubmission;
use App\Models\AiProcessingRun;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\PlagiarismReport;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

function bulkApprovalFixture(): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create([
        'name' => 'First Semester',
        'school_year' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'BA'.strtoupper(bin2hex(random_bytes(3))), 'name' => 'Bulk Approval']);
    $section = Section::create([
        'name' => 'BSCS BA',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'title' => 'Bulk Approval Assignment',
        'instructions' => 'Review the submitted work.',
        'type' => 'essay',
        'max_score' => 20,
        'is_published' => true,
    ]);

    return compact('faculty', 'semester', 'section', 'assignment');
}

function addBulkApprovalSubmission(Assignment $assignment, string $status = 'graded', ?float $score = 12): Submission
{
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::create([
        'user_id' => $user->id,
        'student_no' => 'BA-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
        'first_name' => 'Bulk',
        'last_name' => 'Student '.$user->id,
        'email' => $user->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    Enrollment::create([
        'student_id' => $student->id,
        'section_id' => $assignment->section_id,
        'semester_id' => $assignment->section->semester_id,
        'status' => 'active',
    ]);
    $submission = Submission::create([
        'assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'content' => 'Student work',
        'status' => $status,
    ]);
    if ($score !== null) {
        AiFeedback::create([
            'submission_id' => $submission->id,
            'score' => $score,
            'feedback_json' => ['score' => $score],
            'generated_at' => now(),
            'model_used' => 'test-model',
        ]);
    }

    return $submission;
}

function bulkApprovalPayload(string $operation, string $mode, array $submissions = []): array
{
    return [
        'operation' => $operation,
        'mode' => $mode,
        ...($mode === 'selected' ? [
            'submission_ids' => array_map(fn (Submission $submission) => Crypt::encryptString((string) $submission->id), $submissions),
        ] : []),
    ];
}

it('approves eligible work across pagination and treats zero as a valid score', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = bulkApprovalFixture();
    $submissions = [];
    for ($i = 0; $i < 101; $i++) {
        $submissions[] = addBulkApprovalSubmission($assignment, 'graded', $i === 0 ? 0 : 12);
    }

    $url = route('assignments.submissions.bulk-approve', Crypt::encryptString((string) $assignment->id));
    $this->actingAs($faculty)->postJson($url, bulkApprovalPayload('preview', 'all'))
        ->assertOk()->assertJsonPath('data.eligible', 101);

    $response = $this->actingAs($faculty)->postJson($url, bulkApprovalPayload('approve', 'all'))
        ->assertOk()->assertJsonPath('data.approved', 101);

    expect($response->json('data.status_counts.approved'))->toBe(101)
        ->and($submissions[0]->fresh()->status)->toBe('approved')
        ->and((float) $submissions[0]->grade()->value('raw_score'))->toBe(0.0)
        ->and((bool) $submissions[0]->grade()->value('is_released'))->toBeFalse();
});

it('approves only selected submissions and preserves an existing manual grade', function () {
    ['faculty' => $faculty, 'section' => $section, 'assignment' => $assignment] = bulkApprovalFixture();
    $selected = addBulkApprovalSubmission($assignment, 'graded', 18);
    $other = addBulkApprovalSubmission($assignment, 'graded', 11);
    Grade::create([
        'submission_id' => $selected->id,
        'student_id' => $selected->student_id,
        'section_id' => $section->id,
        'assignment_id' => $assignment->id,
        'raw_score' => 7,
        'max_score' => 20,
        'remarks' => 'Faculty override',
        'is_released' => false,
    ]);

    $url = route('assignments.submissions.bulk-approve', Crypt::encryptString((string) $assignment->id));
    $this->actingAs($faculty)->postJson($url, bulkApprovalPayload('approve', 'selected', [$selected]))
        ->assertOk()->assertJsonPath('data.approved', 1);

    expect($selected->fresh()->status)->toBe('approved')
        ->and((float) $selected->grade()->value('raw_score'))->toBe(7.0)
        ->and($selected->grade()->value('remarks'))->toBe('Faculty override')
        ->and($other->fresh()->status)->toBe('graded');
});

it('returns the original encrypted selection references and filters searched pages authoritatively', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = bulkApprovalFixture();
    $eligible = addBulkApprovalSubmission($assignment, 'graded', 16);
    $approved = addBulkApprovalSubmission($assignment, 'approved', 10);
    $assignmentId = Crypt::encryptString((string) $assignment->id);

    $this->actingAs($faculty);
    $pageOne = $this->getJson(route('assignments.submissions.data', [
        'id' => $assignmentId,
        'per_page' => 1,
        'page' => 1,
        'search' => 'Bulk',
    ]))->assertOk();
    $pageTwo = $this->getJson(route('assignments.submissions.data', [
        'id' => $assignmentId,
        'per_page' => 1,
        'page' => 2,
        'search' => 'Bulk',
    ]))->assertOk();

    $ids = [
        $pageOne->json('data.0.id'),
        $pageTwo->json('data.0.id'),
    ];
    $eligibleReference = collect($ids)
        ->first(fn (string $id) => (int) Crypt::decryptString($id) === $eligible->id);
    expect($pageOne->json('pagination.total'))->toBe(2)
        ->and($pageTwo->json('pagination.current_page'))->toBe(2);

    $preview = $this->postJson(
        route('assignments.submissions.bulk-approve', $assignmentId),
        ['operation' => 'preview', 'mode' => 'selected', 'submission_ids' => $ids],
    )->assertOk()
        ->assertJsonPath('data.eligible', 1)
        ->assertJsonPath('data.already_approved', 1)
        ->assertJsonPath('data.eligible_ids', [$eligibleReference]);

    expect($eligible->fresh()->status)->toBe('graded')
        ->and($approved->fresh()->status)->toBe('approved');
});

it('skips incomplete, flagged, and unresolved AI grading submissions', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = bulkApprovalFixture();
    $pending = addBulkApprovalSubmission($assignment, 'pending', 10);
    $unscored = addBulkApprovalSubmission($assignment, 'graded', null);
    $flagged = addBulkApprovalSubmission($assignment, 'graded', 10);
    $unknown = addBulkApprovalSubmission($assignment, 'graded', 10);
    PlagiarismReport::create([
        'assignment_id' => $assignment->id,
        'student_a_id' => $flagged->student_id,
        'student_b_id' => $pending->student_id,
        'similarity_score' => 95,
        'flagged' => true,
    ]);
    $run = AiProcessingRun::create([
        'owner_id' => $faculty->id,
        'resource_type' => Assignment::class,
        'resource_id' => $assignment->id,
        'processing_type' => 'grading',
        'status' => 'failed',
        'total_work_units' => 1,
    ]);
    AiGradingRunSubmission::create([
        'ai_processing_run_id' => $run->id,
        'submission_id' => $unknown->id,
        'content' => 'saved snapshot',
        'content_hash' => hash('sha256', 'saved snapshot'),
        'status' => 'pending',
        'attempt_count' => 0,
        'error_code' => 'outcome_unknown',
    ]);

    $url = route('assignments.submissions.bulk-approve', Crypt::encryptString((string) $assignment->id));
    $this->actingAs($faculty)->postJson($url, bulkApprovalPayload('preview', 'all'))
        ->assertOk()->assertJsonPath('data.eligible', 0)
        ->assertJsonPath('data.requires_individual_review', 4);
    $this->actingAs($faculty)->postJson($url, bulkApprovalPayload('approve', 'all'))
        ->assertOk()->assertJsonPath('data.skipped', 4);

    expect($pending->fresh()->status)->toBe('pending')
        ->and($unscored->fresh()->status)->toBe('graded')
        ->and($flagged->fresh()->status)->toBe('graded')
        ->and($unknown->fresh()->status)->toBe('graded');
});

it('allows a newer completed AI result to resolve older ordinary failures but retains unknown-outcome protection', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = bulkApprovalFixture();
    $resolved = addBulkApprovalSubmission($assignment, 'graded', 15);
    $unknown = addBulkApprovalSubmission($assignment, 'graded', 13);
    $makeRun = fn () => AiProcessingRun::create([
        'owner_id' => $faculty->id,
        'resource_type' => Assignment::class,
        'resource_id' => $assignment->id,
        'processing_type' => 'grading',
        'status' => 'completed',
        'total_work_units' => 1,
    ]);
    $failedRun = $makeRun();
    $resolvedRun = $makeRun();
    $unknownRun = $makeRun();
    $unknownCompletedRun = $makeRun();

    foreach ([
        [$failedRun, $resolved, 'failed', 'provider_request_failed'],
        [$resolvedRun, $resolved, 'completed', null],
        [$unknownRun, $unknown, 'pending', 'outcome_unknown'],
        [$unknownCompletedRun, $unknown, 'completed', null],
    ] as [$run, $submission, $status, $errorCode]) {
        AiGradingRunSubmission::create([
            'ai_processing_run_id' => $run->id,
            'submission_id' => $submission->id,
            'content' => 'saved snapshot',
            'content_hash' => hash('sha256', 'saved snapshot'),
            'status' => $status,
            'attempt_count' => 1,
            'error_code' => $errorCode,
        ]);
    }

    $assignmentId = Crypt::encryptString((string) $assignment->id);
    $response = $this->actingAs($faculty)->postJson(
        route('assignments.submissions.bulk-approve', $assignmentId),
        bulkApprovalPayload('preview', 'selected', [$resolved, $unknown]),
    )->assertOk();

    expect($response->json('data.eligible'))->toBe(1)
        ->and($response->json('data.requires_individual_review'))->toBe(1);
});

it('is idempotent, rechecks state at approval time, and rejects unauthorized or cross-assignment submissions', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = bulkApprovalFixture();
    $submission = addBulkApprovalSubmission($assignment, 'graded', 9);
    $url = route('assignments.submissions.bulk-approve', Crypt::encryptString((string) $assignment->id));
    $payload = bulkApprovalPayload('approve', 'selected', [$submission]);

    $this->actingAs($faculty)->postJson($url, $payload)->assertOk()->assertJsonPath('data.approved', 1);
    $this->actingAs($faculty)->postJson($url, $payload)->assertOk()->assertJsonPath('data.already_approved', 1);
    expect(Grade::query()->where('submission_id', $submission->id)->count())->toBe(1);

    ['assignment' => $otherAssignment] = bulkApprovalFixture();
    $foreignSubmission = addBulkApprovalSubmission($otherAssignment, 'graded', 9);
    $this->actingAs($faculty)->postJson(
        route('assignments.submissions.bulk-approve', Crypt::encryptString((string) $assignment->id)),
        bulkApprovalPayload('approve', 'selected', [$foreignSubmission]),
    )->assertUnprocessable();

    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $this->actingAs($otherFaculty)->postJson($url, bulkApprovalPayload('approve', 'all'))->assertForbidden();
});

it('rechecks an eligible selection when grading changes before confirmation', function () {
    ['faculty' => $faculty, 'assignment' => $assignment] = bulkApprovalFixture();
    $submission = addBulkApprovalSubmission($assignment, 'graded', 14);
    $url = route('assignments.submissions.bulk-approve', Crypt::encryptString((string) $assignment->id));
    $payload = bulkApprovalPayload('approve', 'selected', [$submission]);
    $preview = bulkApprovalPayload('preview', 'selected', [$submission]);

    $this->actingAs($faculty)->postJson($url, $preview)->assertOk()->assertJsonPath('data.eligible', 1);
    $submission->update(['status' => 'grading']);

    $this->actingAs($faculty)->postJson($url, $payload)
        ->assertOk()
        ->assertJsonPath('data.approved', 0)
        ->assertJsonPath('data.skipped', 1)
        ->assertJsonPath('data.reasons.Grading is incomplete', 1);
    expect($submission->fresh()->status)->toBe('grading')
        ->and($submission->grade()->exists())->toBeFalse();
});
