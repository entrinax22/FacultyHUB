<?php

use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;

function sectionFilteringFixture(): array
{
    $firstFaculty = User::factory()->create(['role' => 'faculty']);
    $secondFaculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create([
        'name' => 'First Semester', 'school_year' => '2026-2027',
        'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'SEC101', 'name' => 'Sections']);
    $first = Section::create(['name' => 'First Section', 'semester_id' => $semester->id, 'subject_id' => $subject->id, 'faculty_id' => $firstFaculty->id]);
    $second = Section::create(['name' => 'Second Section', 'semester_id' => $semester->id, 'subject_id' => $subject->id, 'faculty_id' => $secondFaculty->id]);

    return compact('firstFaculty', 'secondFaculty', 'first', 'second');
}

test('admin all and mine section scopes return the expected records', function () {
    $fixture = sectionFilteringFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $fixture['first']->update(['faculty_id' => $admin->id]);

    $this->actingAs($admin)->getJson(route('sections.data', ['scope' => 'all']))
        ->assertOk()->assertJsonCount(2, 'data');

    $this->actingAs($admin)->getJson(route('sections.data', ['scope' => 'mine']))
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'First Section')
        ->assertJsonPath('filters.scope', 'mine');
});

test('faculty section access remains limited to assigned sections regardless of requested scope', function () {
    $fixture = sectionFilteringFixture();

    $this->actingAs($fixture['firstFaculty'])->getJson(route('sections.data', ['scope' => 'all']))
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'First Section');
});
