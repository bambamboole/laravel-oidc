<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Ui\Actions\DeletePasskeyAction;
use Bambamboole\LaravelOidc\Ui\Components\PasskeyRegistration;
use Bambamboole\LaravelOidc\Ui\Tables\PasskeysTable;
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

test('passkey registration uses the identity provider ceremony routes', function () {
    $component = PasskeyRegistration::make();

    expect($component->optionsUrl)->toBe(route('identity.passkey.registration-options', absolute: false))
        ->and($component->submitUrl)->toBe(route('identity.passkey.store', absolute: false));
});

test('users can delete their own passkey through the lattice action', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $passkey = createPasskey($user);

    $this->actingAs($user)
        ->callAction(DeletePasskeyAction::class, context: ['passkey' => $passkey->id])
        ->assertOk()
        ->assertJsonFragment(['type' => 'reload-component', 'component' => 'oidc.passkeys']);

    expect($user->passkeys()->whereKey($passkey->id)->exists())->toBeFalse();
});

test('users cannot delete another users passkey', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'secret']);
    $passkey = createPasskey($other);

    $this->actingAs($user)
        ->callDeniedAction(DeletePasskeyAction::class, context: ['passkey' => $passkey->id])
        ->assertForbidden();

    expect($other->passkeys()->whereKey($passkey->id)->exists())->toBeTrue();
});

test('the passkeys table lists a created passkey', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    createPasskey($user, 'MacBook Pro');

    $this->actingAs($user)
        ->loadTable(PasskeysTable::class)
        ->assertOk()
        ->assertJsonFragment(['name' => 'MacBook Pro']);
});
