<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('https email verification links work behind a trusted proxy', function () {
    $user = User::factory()->unverified()->create();

    URL::forceScheme('https');
    URL::forceRootUrl('https://localhost');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    expect(parse_url($verificationUrl, PHP_URL_SCHEME))->toBe('https')
        ->and(parse_url($verificationUrl, PHP_URL_HOST))->toBe('localhost');

    // The framework clears TrustProxies static state between tests.
    TrustProxies::at('REMOTE_ADDR');
    TrustProxies::withHeaders(
        \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX,
    );

    $this->actingAs($user)
        ->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'localhost',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'QUERY_STRING' => parse_url($verificationUrl, PHP_URL_QUERY),
        ])
        ->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    URL::forceScheme(null);
    URL::forceRootUrl(null);
});

test('tampered email verification signatures are rejected', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $tamperedUrl = preg_replace('/signature=([^&])/', 'signature=x$1', $verificationUrl);

    $this->actingAs($user)->get($tamperedUrl)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email verification links cannot be used by another authenticated user', function () {
    $owner = User::factory()->unverified()->create();
    $otherUser = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $owner->id, 'hash' => sha1($owner->email)],
    );

    $this->actingAs($otherUser)->get($verificationUrl)->assertForbidden();
    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl)->assertForbidden();

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)->assertForbidden();

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('expired email verification links are rejected', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification notifications are throttled', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    foreach (range(1, 7) as $attempt) {
        $response = $this->actingAs($user)->post(route('verification.send'));
    }

    $response->assertTooManyRequests();
});
