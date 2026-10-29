<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

test('an administrator can edit supported user profile fields without changing the role or password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'faculty']);
    $password = $user->password;

    $response = $this->actingAs($admin)->putJson(route('admin.users.update', Crypt::encryptString((string) $user->id)), [
        'name' => 'Updated Faculty',
        'email' => 'updated-faculty@example.com',
        'password' => 'should-not-be-accepted',
    ]);

    $response->assertOk()->assertJsonPath('data.role', 'faculty');
    expect($user->refresh()->name)->toBe('Updated Faculty')
        ->and($user->email)->toBe('updated-faculty@example.com')
        ->and($user->role)->toBe('faculty')
        ->and($user->password)->toBe($password)
        ->and($user->email_verified_at)->toBeNull();
});

test('admin user profile updates reject invalid and duplicate emails', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $url = route('admin.users.update', Crypt::encryptString((string) $user->id));

    $this->actingAs($admin)->putJson($url, [
        'name' => 'Updated',
        'email' => $other->email,
    ])->assertUnprocessable();

    $this->actingAs($admin)->putJson($url, [
        'name' => 'Updated',
        'email' => 'not-an-email',
    ])->assertUnprocessable();
});
