<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Bambamboole\LaravelOidc\Ui\Actions\DisableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\EnableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\RegenerateRecoveryCodesAction;
use Bambamboole\LaravelOidc\Ui\Forms\ConfirmTwoFactorForm;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

test('the enable action creates a pending factor, recovery codes, and opens the setup modal', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->callAction(EnableTwoFactorAuthenticationAction::class)
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'open-modal', 'modal' => 'oidc.two-factor-setup']);

    expect($user->totpFactors()->whereNull('confirmed_at')->exists())->toBeTrue()
        ->and($user->recoveryCodes()->count())->toBe(8);
});

test('confirming a valid code through the lattice form enables two factor', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TwoFactorManager::class)->enable($user);
    $code = app(Google2FA::class)->getCurrentOtp($factor->secret);

    $this->actingAs($user)
        ->submitForm(ConfirmTwoFactorForm::class, ['code' => $code])
        ->assertRedirect();

    expect($user->totpFactors()->whereNotNull('confirmed_at')->exists())->toBeTrue();
});

test('confirming an invalid code through the lattice form returns a field error', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TwoFactorManager::class)->enable($user);

    $this->actingAs($user)
        ->submitForm(ConfirmTwoFactorForm::class, ['code' => '000000'])
        ->assertInvalid(['code']);

    expect($user->totpFactors()->whereNotNull('confirmed_at')->exists())->toBeFalse();
});

test('the disable action removes factors and recovery codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TwoFactorManager::class)->enable($user);

    $this->actingAs($user)
        ->callAction(DisableTwoFactorAuthenticationAction::class)
        ->assertSuccessful();

    expect($user->totpFactors()->exists())->toBeFalse()
        ->and($user->recoveryCodes()->exists())->toBeFalse();
});

test('the regenerate action replaces recovery codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TwoFactorManager::class)->enable($user);
    $originalCodes = app(TwoFactorManager::class)->recoveryCodes($user);

    $this->actingAs($user)
        ->callAction(RegenerateRecoveryCodesAction::class)
        ->assertSuccessful();

    expect(app(TwoFactorManager::class)->recoveryCodes($user))
        ->toHaveCount(8)
        ->not->toBe($originalCodes);
});
