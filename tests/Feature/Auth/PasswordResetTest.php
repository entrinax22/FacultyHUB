<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('student number verifies the account for direct password reset', function () {
    Notification::fake();

    $user = User::factory()->create();
    Student::create([
        'user_id' => $user->id,
        'student_no' => '2026-12345',
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => $user->email,
        'course' => 'Computer Science',
        'year_level' => 1,
    ]);

    $this->post(route('password.verify'), [
        'mode' => 'student_no',
        'identifier' => '2026-12345',
    ])->assertSessionHas('status');

    $this->assertSame($user->id, session('password_reset_user_id'));
    Notification::assertNothingSent();
});

test('email verifies the account for direct password reset', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.verify'), [
        'mode' => 'email',
        'identifier' => $user->email,
    ])->assertSessionHas('status');

    $this->assertSame($user->id, session('password_reset_user_id'));
    Notification::assertNothingSent();
});

test('password can be changed after account verification', function () {
    $user = User::factory()->create();

    $this->post(route('password.verify'), [
        'mode' => 'email',
        'identifier' => $user->email,
    ]);

    $this->post(route('password.reset.custom'), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('login'));

    $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    $this->assertNull(session('password_reset_user_id'));
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});