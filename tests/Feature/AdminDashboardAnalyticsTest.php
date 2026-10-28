<?php

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardAnalyticsFixture(): array
{
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create([
        'user_id' => $studentUser->id,
        'student_no' => '2026-ANALYTICS',
        'first_name' => 'Analytics',
        'last_name' => 'Student',
        'email' => $studentUser->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    $semester = Semester::create([
        'name' => 'First Semester', 'school_year' => '2026-2027',
        'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'AN101', 'name' => 'Analytics']);
    $section = Section::create(['name' => 'AN 1A', 'semester_id' => $semester->id, 'subject_id' => $subject->id, 'faculty_id' => $faculty->id]);
    Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'semester_id' => $semester->id, 'status' => 'active']);
    $assignment = Assignment::create(['section_id' => $section->id, 'title' => 'Analytics task', 'instructions' => 'Complete it.', 'type' => 'essay', 'max_score' => 100, 'is_published' => true]);
    Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'content' => 'Done', 'status' => 'approved', 'submitted_at' => now()]);

    return compact('faculty', 'semester');
}

test('admin analytics calculates completion from active enrollment assignment slots', function () {
    dashboardAnalyticsFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson(route('admin.dashboard.data'));

    $response->assertOk()->assertJsonPath('data.stats.sections', 1)->assertJsonPath('data.stats.enrollments', 1)->assertJsonPath('data.stats.submissionCompletionRate', 100);
});

test('admin analytics is protected and returns empty aggregates safely', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student)->getJson(route('admin.dashboard.data'))->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->getJson(route('admin.dashboard.data'))
        ->assertOk()->assertJsonPath('data.stats.users', 2)->assertJsonPath('data.stats.sections', 0)
        ->assertJsonPath('data.submissionTrend', [])->assertJsonPath('data.gradeDistribution', []);
});
