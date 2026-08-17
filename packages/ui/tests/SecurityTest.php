<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\RecoveryCodeProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Ui\Actions\RegenerateRecoveryCodesAction;
use Bambamboole\LaravelOidc\Ui\Actions\RevokeFactorAction;
use Bambamboole\LaravelOidc\Ui\Actions\SendVerificationEmailAction;
use Bambamboole\LaravelOidc\Ui\Fragments\RecoveryCodesFragment;
use Bambamboole\LaravelOidc\Ui\Tables\TwoFactorMethodsTable;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Workbench\App\Models\User;

test('the regenerate action replaces recovery codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user);
    $originalCodes = app(RecoveryCodeProvider::class)->generate($user);

    $this->actingAs($user)
        ->callAction(RegenerateRecoveryCodesAction::class)
        ->assertSuccessful();

    expect(app(RecoveryCodeProvider::class)->codes($user))
        ->toHaveCount(8)
        ->not->toBe($originalCodes);
});

test('the recovery codes fragment renders the unused codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $codes = app(RecoveryCodeProvider::class)->generate($user);

    $this->actingAs($user)
        ->loadFragment(RecoveryCodesFragment::class)
        ->assertOk()
        ->assertSee($codes[0])
        ->assertSee(__('oidc-ui::security.recovery-codes.description'), false);
});

test('the recovery codes fragment reports when no codes exist', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->loadFragment(RecoveryCodesFragment::class)
        ->assertOk()
        ->assertSee(__('oidc-ui::security.recovery-codes.none'), false);
});

test('the regenerate action opens the recovery codes modal', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user);
    app(RecoveryCodeProvider::class)->generate($user);

    $this->actingAs($user)
        ->callAction(RegenerateRecoveryCodesAction::class)
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'open-modal', 'modal' => 'oidc.recovery-codes']);
});

test('the methods table lists confirmed enrollments across providers with their role', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user, 'Work phone');
    $user->totpFactors()->update(['confirmed_at' => now()]);
    app(TotpFactorProvider::class)->enroll($user, 'Pending phone');
    $user->passkeys()->create(['name' => 'Yubikey', 'credential_id' => 'credential-id', 'credential' => []]);

    /** @var array<int, array<string, mixed>> $data */
    $data = $this->actingAs($user)->loadTable(TwoFactorMethodsTable::class)->assertOk()->json('data');
    $rows = collect($data);

    expect($rows->pluck('label')->all())->toBe(['Work phone', 'Yubikey'])
        ->and($rows->firstWhere('label', 'Work phone')['role'])->toBe(__('oidc-ui::security.role.second-factor-only'))
        ->and($rows->firstWhere('label', 'Yubikey')['role'])->toBe(__('oidc-ui::security.role.login-and-second-factor'))
        ->and($rows->firstWhere('label', 'Yubikey')['description'])->toBe(__('oidc-ui::auth.two-factor.method.webauthn'));
});

test('the methods table backs the list with a recovery-codes row', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user, 'Work phone');
    $user->totpFactors()->update(['confirmed_at' => now()]);
    app(RecoveryCodeProvider::class)->generate($user);

    /** @var array<int, array<string, mixed>> $data */
    $data = $this->actingAs($user)->loadTable(TwoFactorMethodsTable::class)->assertOk()->json('data');
    $rows = collect($data);
    $backup = $rows->firstWhere('label', __('oidc-ui::security.recovery-codes.heading'));

    expect($backup['description'])->toBe(__('oidc-ui::security.recovery-codes.remaining', ['remaining' => 8, 'total' => 8]))
        ->and($backup['role'])->toBe(__('oidc-ui::security.role.backup'));
});

test('revoking the last challengeable factor takes the recovery codes with it', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TotpFactorProvider::class)->enroll($user);
    $factor->forceFill(['confirmed_at' => now()])->save();
    app(RecoveryCodeProvider::class)->generate($user);

    $this->actingAs($user)
        ->callAction(RevokeFactorAction::class, context: ['provider' => 'totp', 'enrollment' => (string) $factor->getKey()])
        ->assertSuccessful();

    // There is no disable switch any more: emptying the list is what turns
    // two-factor off, and the backup codes must not outlive what they back up.
    expect($user->totpFactors()->exists())->toBeFalse()
        ->and($user->recoveryCodes()->exists())->toBeFalse();
});

test('revoking one of several factors keeps the recovery codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TotpFactorProvider::class)->enroll($user);
    $factor->forceFill(['confirmed_at' => now()])->save();
    $user->passkeys()->create(['name' => 'Yubikey', 'credential_id' => 'credential-id', 'credential' => []]);
    app(RecoveryCodeProvider::class)->generate($user);

    $this->actingAs($user)
        ->callAction(RevokeFactorAction::class, context: ['provider' => 'totp', 'enrollment' => (string) $factor->getKey()])
        ->assertSuccessful();

    expect($user->recoveryCodes()->count())->toBe(8)
        ->and($user->passkeys()->count())->toBe(1);
});

test('the revoke-factor action removes exactly the targeted enrollment', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $first = app(TotpFactorProvider::class)->enroll($user, 'First');
    $user->totpFactors()->update(['confirmed_at' => now()]);
    $second = app(TotpFactorProvider::class)->enroll($user, 'Second');
    $second->forceFill(['confirmed_at' => now()])->save();

    $this->actingAs($user)
        ->callAction(RevokeFactorAction::class, context: ['provider' => 'totp', 'enrollment' => (string) $first->getKey()])
        ->assertSuccessful();

    expect($user->totpFactors()->pluck('id')->all())->toBe([$second->getKey()]);
});

test('the revoke-factor action rejects foreign and unknown enrollments', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'secret']);
    $foreign = app(TotpFactorProvider::class)->enroll($other, 'Other');

    $this->actingAs($user)
        ->callDeniedAction(RevokeFactorAction::class, context: ['provider' => 'totp', 'enrollment' => (string) $foreign->getKey()])
        ->assertForbidden();

    $this->actingAs($user)
        ->callDeniedAction(RevokeFactorAction::class, context: ['provider' => 'webauthn', 'enrollment' => '1'])
        ->assertForbidden();

    expect($other->totpFactors()->exists())->toBeTrue();
});

test('the send-verification-email action notifies an unverified user', function () {
    Notification::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->callAction(SendVerificationEmailAction::class)
        ->assertSuccessful()
        ->assertJsonFragment([
            'type' => 'toast',
            'variant' => 'success',
            'message' => __('oidc-ui::security.verification-sent'),
        ]);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('the send-verification-email action reports an already-verified user without resending', function () {
    Notification::fake();
    $user = User::create([
        'name' => 'M',
        'email' => 'm@example.com',
        'password' => 'secret',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->callAction(SendVerificationEmailAction::class)
        ->assertSuccessful()
        ->assertJsonFragment([
            'type' => 'toast',
            'variant' => 'info',
            'message' => __('oidc-ui::security.already-verified'),
        ]);

    Notification::assertNothingSent();
});

test('the send-verification-email action is forbidden for a user that cannot verify their email', function () {
    $user = new GenericUser(['id' => 1]);

    $this->actingAs($user)
        ->callAction(SendVerificationEmailAction::class)
        ->assertForbidden();
});
