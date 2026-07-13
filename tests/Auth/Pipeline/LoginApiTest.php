<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Pipeline\LoginApi;

it('records deny with a reason', function () {
    $api = new LoginApi;
    $api->deny('blocked-country');

    expect($api->isDenied())->toBeTrue()
        ->and($api->denyReason())->toBe('blocked-country');
});

it('records a requireMfa request', function () {
    $api = new LoginApi;
    expect($api->mfaRequired())->toBeFalse();
    $api->requireMfa();
    expect($api->mfaRequired())->toBeTrue();
});

it('buffers custom id_token claims', function () {
    $api = new LoginApi;
    $api->setIdTokenClaim('groups', ['admin']);

    expect($api->idTokenClaims())->toBe(['groups' => ['admin']]);
});

it('refuses to set a protected id_token claim', function () {
    $api = new LoginApi;
    $api->setIdTokenClaim('sub', 'attacker');
    $api->setIdTokenClaim('amr', ['forged']);

    expect($api->idTokenClaims())->toBe([]);
});

it('buffers access-token claims and refuses protocol-reserved names', function () {
    $api = new LoginApi;

    $api->setAccessTokenClaim('tier', 'gold');
    $api->setAccessTokenClaim('amr', ['hax']); // reserved → refused

    expect($api->accessTokenClaims())->toBe(['tier' => 'gold']);
});
