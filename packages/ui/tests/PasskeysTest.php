<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Ui\Actions\RevokeFactorAction;
use Bambamboole\LaravelOidc\Ui\Components\PasskeyRegistration;
use Bambamboole\LaravelOidc\Ui\Tables\TwoFactorMethodsTable;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Passkey;
use Workbench\App\Models\User;

function createPasskey(User $user, string $name = 'My passkey'): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => 'cred-'.fake()->unique()->uuid(),
        'credential' => ['type' => 'public-key'],
    ]);
}

test('passkey registration uses the generic webauthn enrollment routes', function () {
    $component = PasskeyRegistration::make();

    expect($component->beginUrl)->toBe(route('identity.two-factor.enroll', ['provider' => 'webauthn'], absolute: false))
        ->and($component->confirmUrl)->toBe(route('identity.two-factor.enroll.confirm', ['provider' => 'webauthn'], absolute: false));
});

test('passkey registration reports availability from the ceremony routes', function () {
    expect(PasskeyRegistration::isAvailable())->toBeTrue();

    Route::swap(new Router(new Dispatcher, app()));

    expect(PasskeyRegistration::isAvailable())->toBeFalse();
});

test('users can revoke their own passkey through the methods table action', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $passkey = createPasskey($user);

    $this->actingAs($user)
        ->callAction(RevokeFactorAction::class, context: ['provider' => 'webauthn', 'enrollment' => (string) $passkey->id])
        ->assertOk()
        ->assertJsonFragment(['type' => 'reload-component', 'component' => 'oidc.two-factor.methods']);

    expect($user->passkeys()->whereKey($passkey->id)->exists())->toBeFalse();
});

test('users cannot revoke another users passkey', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'secret']);
    $passkey = createPasskey($other);

    $this->actingAs($user)
        ->callDeniedAction(RevokeFactorAction::class, context: ['provider' => 'webauthn', 'enrollment' => (string) $passkey->id])
        ->assertForbidden();

    expect($other->passkeys()->whereKey($passkey->id)->exists())->toBeTrue();
});

test('the methods table lists only the authenticated users passkeys', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'secret']);
    createPasskey($user, 'My MacBook');
    createPasskey($other, 'Other Device');

    $this->actingAs($user)
        ->loadTable(TwoFactorMethodsTable::class)
        ->assertOk()
        ->assertSee('My MacBook')
        ->assertDontSee('Other Device');
});
