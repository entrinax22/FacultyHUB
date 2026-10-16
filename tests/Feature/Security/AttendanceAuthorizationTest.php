<?php

use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

test('attendance cannot be recorded for a student outside the section enrollment', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);
    $studentUser = User::factory()->create(['role' => 'student']);
    $student = Student::create([
        'user_id' => $studentUser->id,
        'student_no' => '2026-54321',
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => $studentUser->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);
    $semester = Semester::create([
        'name' => 'Term 1',
        'school_year' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'ATT101', 'name' => 'Attendance']);
    $section = Section::create([
        'name' => 'Attendance Section',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
    $session = AttendanceSession::create([
        'section_id' => $section->id,
        'created_by' => $faculty->id,
        'date' => now()->toDateString(),
    ]);

    $this->actingAs($faculty)
        ->post('/attendance-sessions/'.Crypt::encryptString((string) $session->id).'/bulk', [
            'records' => [[
                'student_id' => Crypt::encryptString((string) $student->id),
                'status' => 'present',
            ]],
        ])
        ->assertStatus(422);
});
