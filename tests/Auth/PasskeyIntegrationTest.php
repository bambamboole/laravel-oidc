<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\MultiFactor\FactorRegistry;
use Workbench\App\Models\User;

it('configures native passkey routes from package auth settings', function () {
    expect(route('passkey.login-options'))->toEndWith('/passkeys/login/options')
        ->and(route('passkey.store'))->toEndWith('/user/passkeys')
        ->and(config('passkeys.guard'))->toBe(config('oidc.auth.guard'))
        ->and(config('passkeys.redirect'))->toBe(config('oidc.auth.home'));
});

it('exposes passkeys as webauthn factor enrollments', function () {
    expect(class_implements(User::class))->toHaveKey('Laravel\\Passkeys\\Contracts\\PasskeyUser')
        ->and(app(FactorRegistry::class)->get('webauthn')->key())->toBe('webauthn');
});
