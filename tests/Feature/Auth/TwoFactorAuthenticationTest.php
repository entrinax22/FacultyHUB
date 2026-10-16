<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

function confirmedPasswordSession(): array
{
    return ['auth.password_confirmed_at' => time()];
}

test('two factor enrollment requires confirmation and accepts a valid code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(confirmedPasswordSession())
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $secret = decrypt($user->refresh()->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->actingAs($user)->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => $code])
        ->assertRedirect();

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('invalid two factor enrollment codes are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(confirmedPasswordSession())
        ->post(route('two-factor.enable'));

    $this->actingAs($user)->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertRedirect();

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

test('two factor recovery codes are single use during login', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['single-use-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $codes = $user->recoveryCodes();
    $recoveryCode = $codes[0];

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect(route('dashboard', absolute: false));

    expect($user->refresh()->recoveryCodes())->not->toContain($recoveryCode);

    $this->post(route('logout'));
    $this->flushSession();
    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect(route('two-factor.login'));
});

test('two factor challenge accepts valid codes and rejects invalid codes', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['unused-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $secret = decrypt($user->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'));

    $this->flushSession();
    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['code' => $code])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('disabling two factor requires password confirmation', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->delete(route('two-factor.disable'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($user)->withSession(confirmedPasswordSession())
        ->delete(route('two-factor.disable'))
        ->assertRedirect();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('administrator two factor enforcement can be enabled and disabled', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    config(['auth.require_admin_2fa' => true]);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));

    config(['auth.require_admin_2fa' => false]);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk();
});
