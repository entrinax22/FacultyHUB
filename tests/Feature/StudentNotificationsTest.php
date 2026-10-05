<?php

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Module;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Services\StudentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createStudentNotificationFixture(): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create([
        'user_id' => $studentUser->id,
        'student_no' => '2026-12345',
        'first_name' => 'Casey',
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
    $subject = Subject::create([
        'code' => 'CS101',
        'name' => 'Introduction to Computing',
    ]);
    $section = Section::create([
        'name' => 'BSCS 1A',
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

    return compact('faculty', 'studentUser', 'student', 'semester', 'section');
}

function createStudentNotificationAssignment(Section $section): Assignment
{
    return Assignment::create([
        'section_id' => $section->id,
        'title' => 'Unit 1 Activity',
        'instructions' => 'Complete the activity.',
        'type' => 'essay',
        'max_score' => 20,
        'is_published' => false,
    ]);
}

test('students can list notifications and mark them read', function () {
    ['studentUser' => $studentUser, 'section' => $section] = createStudentNotificationFixture();

    app(StudentNotificationService::class)->notifySectionStudents(
        $section->id,
        [
            'type' => 'assignment',
            'title' => 'New assignment posted',
            'message' => 'Unit 1 Activity is available.',
            'url' => '/my-sections',
        ]
    );

    $response = $this->actingAs($studentUser)
        ->getJson(route('student.notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.title', 'New assignment posted');

    $notificationId = $response->json('data.0.id');

    $this->postJson(route('student.notifications.read', $notificationId))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    $this->postJson(route('student.notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});

test('publishing an assignment notifies active students only', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'semester' => $semester, 'section' => $section] = createStudentNotificationFixture();

    $droppedStudentUser = User::factory()->create(['role' => 'student']);
    $droppedStudent = Student::create([
        'user_id' => $droppedStudentUser->id,
        'student_no' => '2026-54321',
        'first_name' => 'Taylor',
        'last_name' => 'Dropped',
        'email' => $droppedStudentUser->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    Enrollment::create([
        'student_id' => $droppedStudent->id,
        'section_id' => $section->id,
        'semester_id' => $semester->id,
        'status' => 'dropped',
    ]);

    $assignment = createStudentNotificationAssignment($section);
    $encryptedId = Crypt::encryptString((string) $assignment->id);

    $this->actingAs($faculty)
        ->postJson(route('assignments.toggle-publish', $encryptedId))
        ->assertOk();

    $this->assertCount(1, $studentUser->notifications);
    $this->assertCount(0, $droppedStudentUser->notifications);

    $this->postJson(route('assignments.toggle-publish', $encryptedId))
        ->assertOk();

    $this->assertCount(1, $studentUser->notifications);
});

test('updating a published assignment notifies enrolled students', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'section' => $section] = createStudentNotificationFixture();

    $assignment = createStudentNotificationAssignment($section);
    $assignment->update(['is_published' => true]);

    $this->actingAs($faculty)
        ->putJson(
            route(
                'assignments.update',
                Crypt::encryptString((string) $assignment->id)
            ),
            [
                'title' => 'Updated Unit 1 Activity',
                'instructions' => 'Review the updated instructions.',
                'type' => 'essay',
                'max_score' => 20,
                'is_published' => true,
                'proctoring_enabled' => false,
            ]
        )
        ->assertOk();

    $this->assertSame(
        'Assignment updated',
        $studentUser->notifications()->first()->data['title']
    );
    $this->assertSame(1, $studentUser->notifications()->count());
});

test('updating an unpublished draft does not notify students', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'section' => $section] = createStudentNotificationFixture();

    $assignment = createStudentNotificationAssignment($section);

    $this->actingAs($faculty)
        ->putJson(
            route(
                'assignments.update',
                Crypt::encryptString((string) $assignment->id)
            ),
            [
                'title' => 'Draft Activity',
                'instructions' => 'Complete this later.',
                'type' => 'essay',
                'max_score' => 20,
                'is_published' => false,
                'proctoring_enabled' => false,
            ]
        )
        ->assertOk();

    $this->assertCount(0, $studentUser->notifications);
});

test('publishing a module notifies active students', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'section' => $section] = createStudentNotificationFixture();

    $module = Module::create([
        'section_id' => $section->id,
        'title' => 'Week 1 Notes',
        'description' => 'Introductory notes',
        'week_number' => 1,
        'order' => 1,
        'is_published' => false,
    ]);

    $this->actingAs($faculty)
        ->postJson(route(
            'modules.toggle-publish',
            Crypt::encryptString((string) $module->id)
        ))
        ->assertOk();

    $this->assertSame(
        'module',
        $studentUser->notifications()->first()->data['type']
    );
});

test('releasing assignment grades notifies students once', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'student' => $student, 'section' => $section] = createStudentNotificationFixture();

    $assignment = createStudentNotificationAssignment($section);
    Grade::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'assignment_id' => $assignment->id,
        'raw_score' => 18,
        'max_score' => 20,
        'is_released' => false,
    ]);

    $payload = [
        'assignment_id' => Crypt::encryptString((string) $assignment->id),
    ];

    $this->actingAs($faculty)
        ->postJson(route('grades.release'), $payload)
        ->assertOk();

    $this->assertSame(
        'grade_release',
        $studentUser->notifications()->first()->data['type']
    );

    $this->postJson(route('grades.release'), $payload)->assertOk();

    $this->assertCount(1, $studentUser->notifications);
});

test('releasing all section grades notifies affected students once', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'student' => $student, 'section' => $section] = createStudentNotificationFixture();

    $assignment = createStudentNotificationAssignment($section);
    $submission = Submission::create([
        'assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'status' => 'approved',
    ]);
    Grade::create([
        'submission_id' => $submission->id,
        'student_id' => $student->id,
        'section_id' => $section->id,
        'assignment_id' => $assignment->id,
        'raw_score' => 18,
        'max_score' => 20,
        'is_released' => false,
    ]);

    $route = route(
        'sections.class-record.release-all',
        Crypt::encryptString((string) $section->id)
    );

    $this->actingAs($faculty)
        ->postJson($route)
        ->assertOk();

    $this->assertSame(
        'grade_release',
        $studentUser->notifications()->first()->data['type']
    );

    $this->postJson($route)->assertOk();

    $this->assertCount(1, $studentUser->notifications);
});

test('assignment submissions notify the section faculty and administrators', function () {
    ['faculty' => $faculty, 'studentUser' => $studentUser, 'section' => $section] = createStudentNotificationFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $otherFaculty = User::factory()->create(['role' => 'faculty']);
    $assignment = createStudentNotificationAssignment($section);
    $assignment->update(['is_published' => true]);
    Queue::fake();

    $this->actingAs($studentUser)
        ->postJson(
            route(
                'assignments.submit.store',
                Crypt::encryptString((string) $assignment->id)
            ),
            ['content' => 'This is my completed assignment response.']
        )
        ->assertOk();

    $this->assertSame(
        'assignment_submission',
        $faculty->notifications()->first()->data['type']
    );
    $this->assertSame(
        'assignment_submission',
        $admin->notifications()->first()->data['type']
    );
    $this->assertCount(0, $otherFaculty->notifications);
    $this->assertCount(0, $studentUser->notifications);

    $this->actingAs($faculty)
        ->getJson(route('staff.notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.title', 'Assignment submitted');
});
