<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\EnrollableFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Data\EnrollmentOption;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorRole;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorSetupKind;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorChallenge;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorVerification;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\RecoveryCodeProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Ui\Forms\TwoFactorSetupForm;
use Illuminate\Contracts\Auth\Authenticatable;
use Lattice\Core\Support\Wire;
use Lattice\Facades\Effects;
use Lattice\Form\Components\Choice;
use Lattice\Form\Components\Form;
use Lattice\Ui\Effects\Builtin\OpenModal;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

/**
 * The setup wizard: step one picks an enrollment option, step two is prepared by
 * Lattice's resolve sub-request, and Finish confirms in a single submit. These
 * cover both halves through the endpoint the browser uses, so the resolve's
 * side effect — beginning the enrollment — is exercised the way it really runs.
 */
function setupUser(): User
{
    return User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
}

/** The step-one picker, narrowed from the field list so its options are typed. */
function pickerChoice(): Choice
{
    $choice = app(TwoFactorSetupForm::class)
        ->definition(Form::make('form'), request())
        ->fields()
        ->firstWhere(fn ($field): bool => $field->name() === 'option');

    assert($choice instanceof Choice);

    return $choice;
}

/**
 * @return array<string, mixed>
 */
function resolveSetupField(mixed $test, User $user, string $option): array
{
    return $test->actingAs($user)
        ->submitForm(TwoFactorSetupForm::class, ['_sub' => 'resolve', 'option' => $option])
        ->assertOk()
        ->json('fields.setup.props');
}

test('the picker offers every enrollment option with what it is good for', function () {
    $choice = pickerChoice();

    expect(array_column($choice->options, 'value'))->toBe(['passkey', 'security_key', 'totp'])
        ->and($choice->options[0]->data['recommended'])->toBeTrue()
        ->and($choice->options[0]->data['role'])->toBe(__('oidc-ui::security.role.login-and-second-factor'))
        ->and($choice->options[0]->data['icon'])->toBe('fingerprint')
        ->and($choice->options[2]->data['role'])->toBe(__('oidc-ui::security.role.second-factor-only'))
        ->and($choice->options[2]->data['description'])->toBe(__('oidc-ui::security.option.totp.description'));
});

test('the picker carries the recommended option as its value', function () {
    $choice = pickerChoice();

    expect($choice->value)->toBe('passkey');
});

test('the picker renders each option as a card bound to its data', function () {
    $choice = pickerChoice();

    /** @var array<string, mixed> $node */
    $node = json_decode((string) json_encode(Wire::toWire([$choice])[0]), true);

    $bound = [];
    $collect = function (array $nodes) use (&$collect, &$bound): void {
        foreach ($nodes as $child) {
            foreach ($child['props']['dataBindings'] ?? [] as $key) {
                $bound[] = $key;
            }
            $collect($child['schema'] ?? []);
        }
    };
    $collect($node['props']['optionSchema']);

    // The schema ships once and binds per option, so adding a provider needs no
    // rendering code of its own.
    expect($node['props']['optionSchema'])->toHaveCount(1)
        ->and($bound)->toEqualCanonicalizing(['icon', 'label', 'role', 'description']);
});

test('resolving a code option begins the enrollment and returns its setup payload', function () {
    $user = setupUser();

    $props = resolveSetupField($this, $user, 'totp');

    expect($props['kind'])->toBe('code')
        ->and($props['secret'])->toBeString()
        ->and($props['qrSvg'])->toContain('<svg')
        ->and($user->totpFactors()->whereNull('confirmed_at')->count())->toBe(1)
        ->and($user->recoveryCodes()->count())->toBe(0);
});

test('resolving again for the same option reuses the pending enrollment', function () {
    $user = setupUser();

    $first = resolveSetupField($this, $user, 'totp');
    $second = resolveSetupField($this, $user, 'totp');

    expect($second['secret'])->toBe($first['secret'])
        ->and($user->totpFactors()->count())->toBe(1);
});

test('resolving a ceremony option asks the browser for the named authenticator', function (string $option, string $attachment) {
    config(['passkeys.user_handle_secret' => 'user-handle-secret']);

    $props = resolveSetupField($this, setupUser(), $option);

    expect($props['kind'])->toBe('ceremony')
        ->and($props['enrollmentId'])->toBe('pending')
        ->and($props['webauthnOptions']['authenticatorSelection']['authenticatorAttachment'])->toBe($attachment);
})->with([
    ['passkey', 'platform'],
    ['security_key', 'cross-platform'],
]);

test('switching the ceremony option reissues the challenge', function () {
    config(['passkeys.user_handle_secret' => 'user-handle-secret']);
    $user = setupUser();

    $passkey = resolveSetupField($this, $user, 'passkey');
    $securityKey = resolveSetupField($this, $user, 'security_key');

    expect($securityKey['webauthnOptions']['challenge'])->not->toBe($passkey['webauthnOptions']['challenge']);
});

test('finishing the wizard confirms the factor and shows the fresh recovery codes', function () {
    // Observe Lattice's own effect flasher rather than Inertia's flash-bag
    // internals, which vary across inertia-laravel versions.
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

    $user = setupUser();
    resolveSetupField($this, $user, 'totp');
    $factor = app(TotpFactorProvider::class)->latestPendingFactor($user);

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->submitForm(TwoFactorSetupForm::class, [
            'option' => 'totp',
            'setup' => app(Google2FA::class)->getCurrentOtp($factor->secret),
        ])
        ->assertRedirect();

    $openedModals = array_map(
        static fn (OpenModal $effect): ?string => $effect->node->componentId(),
        array_values(array_filter($recorder->effects, static fn (object $effect): bool => $effect instanceof OpenModal)),
    );

    expect($openedModals)->toBe(['oidc.recovery-codes'])
        ->and($user->totpFactors()->whereNotNull('confirmed_at')->exists())->toBeTrue()
        ->and($user->recoveryCodes()->count())->toBe(8);
});

test('a second factor is confirmed without reissuing recovery codes', function () {
    $user = setupUser();
    $first = app(TotpFactorProvider::class)->enroll($user);
    $first->forceFill(['confirmed_at' => now()])->save();
    app(RecoveryCodeProvider::class)->generate($user);
    $codes = app(RecoveryCodeProvider::class)->codes($user);

    resolveSetupField($this, $user, 'totp');
    $pending = app(TotpFactorProvider::class)->latestPendingFactor($user);

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->submitForm(TwoFactorSetupForm::class, [
            'option' => 'totp',
            'setup' => app(Google2FA::class)->getCurrentOtp($pending->secret),
        ])
        ->assertRedirect();

    expect(app(RecoveryCodeProvider::class)->codes($user))
        ->toBe($codes);
});

test('a confirmation that does not prove the setup returns a field error', function () {
    $user = setupUser();
    resolveSetupField($this, $user, 'totp');

    $this->actingAs($user)
        ->submitForm(TwoFactorSetupForm::class, ['option' => 'totp', 'setup' => '000000'])
        ->assertInvalid(['setup']);

    expect($user->totpFactors()->whereNotNull('confirmed_at')->exists())->toBeFalse();
});

test('a confirmed factor never has its secret shown again', function () {
    $user = setupUser();
    $confirmed = app(TotpFactorProvider::class)->enroll($user);
    $confirmed->forceFill(['confirmed_at' => now()])->save();

    $props = resolveSetupField($this, $user, 'totp');

    // Re-enrolling alongside a confirmed factor opens a fresh pending one; the
    // confirmed secret stays where it was written and is never re-exposed.
    expect($props['secret'])->not->toBe($confirmed->secret)
        ->and($user->totpFactors()->count())->toBe(2);
});

test('the host can point the wizard at its own recovery codes modal', function () {
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

    $user = setupUser();
    $context = ['recovery_codes_modal' => 'host.custom-codes'];
    $this->actingAs($user)->submitForm(TwoFactorSetupForm::class, ['_sub' => 'resolve', 'option' => 'totp'], $context);
    $factor = app(TotpFactorProvider::class)->latestPendingFactor($user);

    $this->actingAs($user)->withHeader('X-Inertia', 'true')->submitForm(TwoFactorSetupForm::class, [
        'option' => 'totp',
        'setup' => app(Google2FA::class)->getCurrentOtp($factor->secret),
    ], $context)->assertRedirect();

    expect(array_map(
        static fn (OpenModal $effect): ?string => $effect->node->componentId(),
        array_values(array_filter($recorder->effects, static fn (object $effect): bool => $effect instanceof OpenModal)),
    ))->toBe(['host.custom-codes']);
});

test('a ceremony credential submitted as a JSON string reaches the provider decoded', function () {
    $provider = new class implements EnrollableFactorProvider
    {
        /** @var array<string, mixed>|null */
        public ?array $confirmedWith = null;

        public function key(): string
        {
            return 'fake-ceremony';
        }

        public function isBackup(): bool
        {
            return false;
        }

        public function enrollmentOptions(): array
        {
            return [new EnrollmentOption('fake_ceremony', $this->key(), FactorRole::SecondFactorOnly, FactorSetupKind::Ceremony)];
        }

        public function enrollments(Authenticatable $user): array
        {
            return [new FactorEnrollment($this->key(), 'pending', 'Fake', null, null)];
        }

        public function beginEnrollment(Authenticatable $user, ?EnrollmentOption $option = null, ?string $name = null): FactorEnrollment
        {
            return $this->enrollments($user)[0];
        }

        public function confirmEnrollment(Authenticatable $user, FactorEnrollment $enrollment, array $input): bool
        {
            $this->confirmedWith = $input;

            return true;
        }

        public function revoke(Authenticatable $user, FactorEnrollment $enrollment): void {}

        public function beginChallenge(Authenticatable $user, FactorEnrollment $enrollment): FactorChallenge
        {
            return new FactorChallenge($enrollment);
        }

        public function verify(Authenticatable $user, FactorChallenge $challenge, array $input): FactorVerification
        {
            return new FactorVerification(false);
        }
    };
    app(FactorRegistry::class)->register($provider);

    // The browser's attestation is a nested object, which the client submits as
    // a JSON string in a hidden input — Inertia serializes the DOM, and a nested
    // object cannot be mounted as one.
    $credential = ['id' => 'abc', 'response' => ['clientDataJSON' => 'payload']];

    $this->actingAs(setupUser())
        ->withHeader('X-Inertia', 'true')
        ->submitForm(TwoFactorSetupForm::class, [
            'option' => 'fake_ceremony',
            'setup' => [
                'credential' => (string) json_encode($credential),
                'name' => 'My key',
            ],
        ])
        ->assertRedirect();

    expect($provider->confirmedWith)->toBe(['credential' => $credential, 'name' => 'My key']);
});

test('an unknown enrollment option is rejected', function () {
    $this->actingAs(setupUser())
        ->submitForm(TwoFactorSetupForm::class, ['option' => 'sms', 'setup' => '000000'])
        ->assertInvalid(['option']);
});
