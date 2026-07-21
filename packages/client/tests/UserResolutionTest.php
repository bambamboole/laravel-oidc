<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Bambamboole\LaravelOidcClient\OidcClientManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Workbench\App\Models\User;

it('resolves a user through the configured seam', function () {
    OidcClient::resolveUsersUsing(fn (string $sub, array $claims): ?Authenticatable => User::find($sub));

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $resolved = app(OidcClientManager::class)->resolveUser((string) $user->getKey(), ['sub' => (string) $user->getKey()]);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($user))->toBeTrue();
});

it('falls back to resolving the login guard provider by sub as primary key', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $resolved = app(OidcClientManager::class)->resolveUser((string) $user->getKey(), []);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($user))->toBeTrue();
});
