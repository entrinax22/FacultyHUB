<?php

use App\Http\Controllers\AdminController;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reports aggregate the grade distribution without loading released grades', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create([
        'user_id' => $studentUser->id,
        'student_no' => '2026-00001',
        'first_name' => 'Report',
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
    $subject = Subject::create(['code' => 'CS101', 'name' => 'Computing']);
    $section = Section::create([
        'name' => 'BSCS 1A',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'title' => 'Report assignment',
        'instructions' => 'Complete the task.',
        'type' => 'essay',
        'max_score' => 100,
        'is_published' => true,
    ]);

    foreach ([[89.6, 100, '90–100'], [80, 100, '80–89'], [75, 100, '75–79'], [60, 100, '60–74'], [0, 0, 'Below 60']] as [$rawScore, $maxScore]) {
        Grade::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'assignment_id' => $assignment->id,
            'raw_score' => $rawScore,
            'max_score' => $maxScore,
            'is_released' => true,
        ]);
    }

    $data = app(AdminController::class)->reports(request())->getData(true)['data'];

    expect(collect($data['gradeDistribution'])->pluck('count', 'label')->all())
        ->toBe([
            '90–100' => 1,
            '80–89' => 1,
            '75–79' => 1,
            '60–74' => 1,
            'Below 60' => 1,
        ]);
    expect($data['enrollmentBySemester'])->toHaveCount(1);
});
