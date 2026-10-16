<?php

use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Module;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

function securityTestSection(User $faculty): Section
{
    $semester = Semester::create([
        'name' => 'Term 1',
        'school_year' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $subject = Subject::create([
        'code' => 'SEC101',
        'name' => 'Security',
    ]);

    return Section::create([
        'name' => 'Security Section',
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
    ]);
}

test('faculty cannot access another faculty member section resources', function () {
    $owner = User::factory()->create(['role' => 'faculty']);
    $viewer = User::factory()->create(['role' => 'faculty']);
    $section = securityTestSection($owner);

    $this->actingAs($viewer)
        ->get('/sections/'.Crypt::encryptString((string) $section->id).'/modules')
        ->assertForbidden();
});

test('faculty cannot access another faculty member modules or assignments', function () {
    $owner = User::factory()->create(['role' => 'faculty']);
    $viewer = User::factory()->create(['role' => 'faculty']);
    $section = securityTestSection($owner);
    $module = Module::create(['section_id' => $section->id, 'title' => 'Private Module']);
    $assignment = Assignment::create([
        'section_id' => $section->id,
        'module_id' => $module->id,
        'title' => 'Private Assignment',
        'instructions' => 'Instructions',
        'type' => 'essay',
    ]);

    $this->actingAs($viewer)
        ->get(route('modules.show', Crypt::encryptString((string) $module->id)))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('assignments.show', Crypt::encryptString((string) $assignment->id)))
        ->assertForbidden();
});

test('faculty owners can access their own module resources', function () {
    $owner = User::factory()->create(['role' => 'faculty']);
    $section = securityTestSection($owner);
    $module = Module::create(['section_id' => $section->id, 'title' => 'Owned Module']);

    $this->actingAs($owner)
        ->get(route('modules.show', Crypt::encryptString((string) $module->id)))
        ->assertOk();
});

test('students cannot access faculty or administrator endpoints', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('sections.index'))
        ->assertForbidden();

    $this->actingAs($student)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

