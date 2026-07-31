<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\RecoveryCodeProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Ui\Actions\DisableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\EnableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\RegenerateRecoveryCodesAction;
use Bambamboole\LaravelOidc\Ui\Actions\RevokeFactorAction;
use Bambamboole\LaravelOidc\Ui\Actions\SendVerificationEmailAction;
use Bambamboole\LaravelOidc\Ui\Forms\ConfirmTwoFactorForm;
use Bambamboole\LaravelOidc\Ui\Fragments\RecoveryCodesFragment;
use Bambamboole\LaravelOidc\Ui\Fragments\TwoFactorSetupFragment;
use Bambamboole\LaravelOidc\Ui\Tables\TwoFactorMethodsTable;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Lattice\Lattice\Effects\Builtin\OpenModal;
use Lattice\Lattice\Facades\Effects;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

test('the enable action creates a pending factor and opens the setup modal', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->callAction(EnableTwoFactorAuthenticationAction::class)
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'open-modal', 'modal' => 'oidc.two-factor-setup']);

    expect($user->totpFactors()->whereNull('confirmed_at')->count())->toBe(1)
        ->and($user->recoveryCodes()->count())->toBe(0);
});

test('a repeated enable action reuses the pending factor instead of stacking rows', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->callAction(EnableTwoFactorAuthenticationAction::class)->assertSuccessful();
    $this->actingAs($user)->callAction(EnableTwoFactorAuthenticationAction::class)->assertSuccessful();

    expect($user->totpFactors()->count())->toBe(1);
});

test('confirming a valid code through the lattice form enables two factor and backfills recovery codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TotpFactorProvider::class)->enroll($user);
    $code = app(Google2FA::class)->getCurrentOtp($factor->secret);

    $this->actingAs($user)
        ->submitForm(ConfirmTwoFactorForm::class, ['code' => $code])
        ->assertRedirect();

    expect($user->totpFactors()->whereNotNull('confirmed_at')->exists())->toBeTrue()
        ->and($user->recoveryCodes()->count())->toBe(8);
});

test('confirming an invalid code through the lattice form returns a field error', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user);

    $this->actingAs($user)
        ->submitForm(ConfirmTwoFactorForm::class, ['code' => '000000'])
        ->assertInvalid(['code']);

    expect($user->totpFactors()->whereNotNull('confirmed_at')->exists())->toBeFalse();
});

test('the disable action removes factors and recovery codes', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user);
    app(RecoveryCodeProvider::class)->generate($user);

    $this->actingAs($user)
        ->callAction(DisableTwoFactorAuthenticationAction::class)
        ->assertSuccessful();

    expect($user->totpFactors()->exists())->toBeFalse()
        ->and($user->recoveryCodes()->exists())->toBeFalse();
});

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

test('the two-factor setup fragment shows the QR code and setup key for a pending factor', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TotpFactorProvider::class)->enroll($user);

    $this->actingAs($user)
        ->loadFragment(TwoFactorSetupFragment::class)
        ->assertOk()
        ->assertSee(__('oidc-ui::security.two-factor.setup-key'), false)
        ->assertSee($factor->secret, false);
});

test('the two-factor setup fragment reports an already-confirmed factor instead of re-showing the secret', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TotpFactorProvider::class)->enroll($user);
    $user->totpFactors()->update(['confirmed_at' => now()]);

    $this->actingAs($user)
        ->loadFragment(TwoFactorSetupFragment::class)
        ->assertOk()
        ->assertSee(__('oidc-ui::security.two-factor.already-enabled'), false)
        ->assertDontSee($factor->secret, false);
});

test('the enable action rejects an unknown provider context', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->callAction(EnableTwoFactorAuthenticationAction::class, context: ['provider' => 'unknown'])
        ->assertNotFound();
});

test('the enable action begins a webauthn enrollment and opens the setup modal', function () {
    config(['passkeys.user_handle_secret' => 'user-handle-secret']);
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->callAction(EnableTwoFactorAuthenticationAction::class, context: ['provider' => 'webauthn'])
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'open-modal', 'modal' => 'oidc.two-factor-setup']);

    expect(session('oidc.webauthn.enrollment'))->toBeArray();
});

test('the setup fragment renders the passkey ceremony for the webauthn provider', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->loadFragment(TwoFactorSetupFragment::class, context: ['provider' => 'webauthn'])
        ->assertOk()
        ->assertSee('oidc.passkey-registration');
});

test('the disable action removes code-based factors but keeps passkeys', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user);
    app(RecoveryCodeProvider::class)->generate($user);
    $user->passkeys()->create(['name' => 'Yubikey', 'credential_id' => 'credential-id', 'credential' => []]);

    $this->actingAs($user)
        ->callAction(DisableTwoFactorAuthenticationAction::class)
        ->assertSuccessful();

    expect($user->totpFactors()->exists())->toBeFalse()
        ->and($user->recoveryCodes()->exists())->toBeFalse()
        ->and($user->passkeys()->count())->toBe(1);
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

test('confirming the first factor opens the recovery codes modal once', function () {
    // Observe Lattice's own effect flasher instead of Inertia's flash-bag
    // session internals, which vary across inertia-laravel versions.
    $recorder = new class
    {
        /** @var list<object> */
        public array $effects = [];

        public function flash(object ...$effects): void
        {
            array_push($this->effects, ...$effects);
        }
    };
    Effects::swap($recorder);

    $openedModals = fn (): array => array_map(
        static fn (OpenModal $effect): string => $effect->modal,
        array_values(array_filter($recorder->effects, static fn (object $effect): bool => $effect instanceof OpenModal)),
    );

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $factor = app(TotpFactorProvider::class)->enroll($user);
    $code = app(Google2FA::class)->getCurrentOtp($factor->secret);

    $this->actingAs($user)
        ->submitForm(ConfirmTwoFactorForm::class, ['code' => $code])
        ->assertRedirect();

    expect($openedModals())->toBe(['oidc.recovery-codes']);

    // A later re-enrollment confirmation must not regenerate the codes or
    // re-open the modal — the policy only backfills when none exist.
    $recorder->effects = [];
    $codes = app(RecoveryCodeProvider::class)->codes($user);
    $second = app(TotpFactorProvider::class)->enroll($user);
    $secondCode = app(Google2FA::class)->getCurrentOtp($second->secret);

    $this->actingAs($user)
        ->submitForm(ConfirmTwoFactorForm::class, ['code' => $secondCode])
        ->assertRedirect();

    expect(app(RecoveryCodeProvider::class)->codes($user))->toBe($codes)
        ->and($openedModals())->toBe([]);
});

test('the enable action opens a context-provided modal', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->callAction(EnableTwoFactorAuthenticationAction::class, context: ['modal' => 'host.custom-setup'])
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'open-modal', 'modal' => 'host.custom-setup']);
});

test('the methods table lists confirmed non-backup enrollments across providers', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    app(TotpFactorProvider::class)->enroll($user, 'Work phone');
    $user->totpFactors()->update(['confirmed_at' => now()]);
    app(TotpFactorProvider::class)->enroll($user, 'Pending phone');
    app(RecoveryCodeProvider::class)->generate($user);
    $user->passkeys()->create(['name' => 'Yubikey', 'credential_id' => 'credential-id', 'credential' => []]);

    $this->actingAs($user)
        ->loadTable(TwoFactorMethodsTable::class)
        ->assertOk()
        ->assertSee('Work phone')
        ->assertSee('Yubikey')
        ->assertSee(__('oidc-ui::auth.two-factor.method.totp'))
        ->assertSee(__('oidc-ui::auth.two-factor.method.webauthn'))
        ->assertDontSee('Pending phone');
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

test('the setup fragment rejects an unknown provider context', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->loadFragment(TwoFactorSetupFragment::class, context: ['provider' => 'unknown'])
        ->assertNotFound();
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
