<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('deleting an account also removes its unlinked student profile', function () {
    $user = User::factory()->create();
    $student = Student::create([
        'user_id' => null,
        'student_no' => '2026-12345',
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => $user->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('students', ['id' => $student->id]);
    $this->assertDatabaseMissing('students', ['student_no' => '2026-12345']);
});
