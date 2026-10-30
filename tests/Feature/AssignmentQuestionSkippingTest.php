<?php

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\GradingComponent;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $this->studentUser = $studentUser;
    $this->student = Student::create([
        'user_id' => $studentUser->id,
        'student_no' => 'SKIP-1001',
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => $studentUser->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    $semester = Semester::create([
        'name' => 'Term 1', 'school_year' => '2026-2027',
        'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'SK101', 'name' => 'Skip setting']);
    $this->section = Section::create([
        'name' => 'Section A', 'semester_id' => $semester->id,
        'subject_id' => $subject->id, 'faculty_id' => $faculty->id,
    ]);
    $this->semester = $semester;
    GradingComponent::create([
        'section_id' => $this->section->id, 'name' => 'Tasks',
        'weight_percentage' => 100, 'max_score' => 100, 'order' => 1,
    ]);
    Enrollment::create([
        'student_id' => $this->student->id, 'section_id' => $this->section->id,
        'semester_id' => $semester->id, 'status' => 'active',
    ]);
    $this->actingAs($faculty);
});

function questionSkippingPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Monitored MCQ',
        'instructions' => 'Answer the questions.',
        'type' => 'mcq',
        'category' => 'exam',
        'max_score' => 10,
        'duration_minutes' => 30,
        'proctoring_enabled' => true,
        'is_published' => true,
        'questions' => [[
            'question' => 'Question one?',
            'points' => 10,
            'choices' => [
                ['choice_text' => 'A', 'is_correct' => true],
                ['choice_text' => 'B', 'is_correct' => false],
            ],
        ]],
    ], $overrides);
}

test('new proctored MCQ assignments default to disallowing question skipping', function () {
    $this->postJson(route('sections.assignments.store', Crypt::encryptString((string) $this->section->id)), questionSkippingPayload())
        ->assertCreated()
        ->assertJsonPath('data.allow_question_skipping', false);

    expect((bool) Assignment::latest('id')->value('allow_question_skipping'))->toBeFalse();
});

test('instructor setting persists and is retained when proctoring is disabled', function () {
    $this->postJson(route('sections.assignments.store', Crypt::encryptString((string) $this->section->id)), questionSkippingPayload([
        'allow_question_skipping' => true,
    ]))->assertCreated();
    $assignment = Assignment::latest('id')->firstOrFail();
    expect($assignment->allow_question_skipping)->toBeTrue();

    $this->putJson(route('assignments.update', Crypt::encryptString((string) $assignment->id)), questionSkippingPayload([
        'allow_question_skipping' => false,
    ]))->assertOk()->assertJsonPath('data.allow_question_skipping', false);
    expect($assignment->fresh()->allow_question_skipping)->toBeFalse();

    $this->putJson(route('assignments.update', Crypt::encryptString((string) $assignment->id)), questionSkippingPayload([
        'allow_question_skipping' => true,
    ]))->assertOk();

    $payload = questionSkippingPayload([
        'proctoring_enabled' => false,
        'allow_question_skipping' => false,
    ]);
    $this->putJson(route('assignments.update', Crypt::encryptString((string) $assignment->id)), $payload)
        ->assertOk()
        ->assertJsonPath('data.allow_question_skipping', true);

    expect($assignment->fresh()->allow_question_skipping)->toBeTrue();
});

test('edit form exposes the persisted setting even when proctoring is disabled', function () {
    $assignment = Assignment::create([
        'section_id' => $this->section->id,
        'title' => 'Saved choice', 'instructions' => 'Answer.', 'type' => 'mcq',
        'category' => 'exam', 'max_score' => 10, 'duration_minutes' => 30,
        'proctoring_enabled' => false, 'allow_question_skipping' => true,
    ]);

    $this->get(route('assignments.edit', Crypt::encryptString((string) $assignment->id)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.allow_question_skipping', true)
            ->where('assignment.proctoring_enabled', false));
});

test('student exam configuration carries the instructor setting', function () {
    $assignment = Assignment::create([
        'section_id' => $this->section->id,
        'title' => 'Monitored exam', 'instructions' => 'Answer.', 'type' => 'mcq',
        'category' => 'exam', 'max_score' => 10, 'duration_minutes' => 30,
        'proctoring_enabled' => true, 'allow_question_skipping' => false,
        'is_published' => true,
    ]);
    $this->actingAs($this->studentUser)
        ->get(route('assignments.submit', Crypt::encryptString((string) $assignment->id)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('exam.proctoring_enabled', true)
            ->where('exam.allow_question_skipping', false));
});
