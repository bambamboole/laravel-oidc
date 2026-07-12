<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\AuthenticationContext;

it('starts a fresh amr list, overwriting any previous value', function () {
    session()->put(AuthenticationContext::SESSION_KEY, ['stale']);

    app(AuthenticationContext::class)->start('pwd');

    expect(session()->get(AuthenticationContext::SESSION_KEY))->toBe(['pwd']);
});

it('initializes the amr list from empty when adding without a prior start', function () {
    app(AuthenticationContext::class)->add('otp');

    expect(session()->get(AuthenticationContext::SESSION_KEY))->toBe(['otp']);
});

it('appends factor methods and de-dupes while preserving order', function () {
    $context = app(AuthenticationContext::class);
    $context->start('pwd');
    $context->add('otp');
    $context->add('otp', 'pwd');

    expect(session()->get(AuthenticationContext::SESSION_KEY))->toBe(['pwd', 'otp']);
});

it('derives acr from the number of methods', function () {
    expect(AuthenticationContext::deriveAcr([]))->toBeNull()
        ->and(AuthenticationContext::deriveAcr(['pwd']))->toBe('1')
        ->and(AuthenticationContext::deriveAcr(['pwd', 'otp']))->toBe('2')
        ->and(AuthenticationContext::deriveAcr(['pwd', 'webauthn']))->toBe('2');
});
