<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\MultiFactor\Models\TotpFactor;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

it('enables and confirms a TOTP factor through Fortify-compatible routes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('two-factor.enable'))
        ->assertOk();

    $factor = TotpFactor::query()->firstOrFail();

    expect($factor->authenticatable->is($user))->toBeTrue()
        ->and($factor->confirmed_at)->toBeNull()
        ->and($user->recoveryCodes()->count())->toBe(8)
        ->and(DB::table('oidc_totp_factors')->value('secret'))->not->toBe($factor->secret);

    $this->actingAs($user)->getJson(route('two-factor.qr-code'))
        ->assertOk()
        ->assertJsonStructure(['svg', 'url']);

    $this->actingAs($user)->getJson(route('two-factor.secret-key'))
        ->assertOk()
        ->assertJson(['secretKey' => $factor->secret]);

    $code = app(Google2FA::class)->getCurrentOtp($factor->secret);

    $this->actingAs($user)
        ->postJson(route('two-factor.confirm'), ['code' => $code])
        ->assertOk();

    expect($factor->refresh()->confirmed_at)->not->toBeNull();
});

it('lists regenerates and disables recovery credentials', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('two-factor.enable'))
        ->assertOk();

    $originalCodes = $this->actingAs($user)
        ->getJson(route('two-factor.recovery-codes'))
        ->assertOk()
        ->json();

    expect($originalCodes)->toHaveCount(8);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('two-factor.regenerate-recovery-codes'))
        ->assertOk();

    $newCodes = $this->actingAs($user)->getJson(route('two-factor.recovery-codes'))->json();

    expect($newCodes)->toHaveCount(8)->not->toBe($originalCodes);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->deleteJson(route('two-factor.disable'))
        ->assertOk();

    expect($user->totpFactors()->count())->toBe(0)
        ->and($user->recoveryCodes()->count())->toBe(0);
});

it('allows multiple TOTP enrollments per user', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $user->totpFactors()->createMany([
        ['name' => 'Phone', 'secret' => 'FIRSTSECRET'],
        ['name' => 'Tablet', 'secret' => 'SECONDSECRET'],
    ]);

    expect($user->totpFactors)->toHaveCount(2);
});

it('requires recent password confirmation to manage factors', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->post(route('two-factor.enable'))
        ->assertRedirect(route('password.confirm'));

    expect($user->totpFactors()->exists())->toBeFalse();
});
