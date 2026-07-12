<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Clients\FirstPartyClientConfig;

it('resolves the new first-party client configuration', function () {
    config([
        'oidc.first_party' => ['client_id' => 'new-client', 'trusted' => true],
        'oidc.first_party_client' => 'legacy-client',
        'oidc.trusted_clients' => ['additional-client'],
    ]);

    $config = FirstPartyClientConfig::fromConfig();

    expect($config->clientId())->toBe('new-client')
        ->and($config->isConfigured())->toBeTrue()
        ->and($config->isTrusted('new-client'))->toBeTrue()
        ->and($config->isTrusted('additional-client'))->toBeTrue()
        ->and($config->isTrusted('legacy-client'))->toBeFalse();
});

it('falls back to the legacy scalar and legacy trust list', function () {
    config([
        'oidc.first_party' => ['client_id' => null, 'trusted' => false],
        'oidc.first_party_client' => 'legacy-client',
        'oidc.trusted_clients' => ['legacy-client', 'additional-client'],
    ]);

    $config = FirstPartyClientConfig::fromConfig();

    expect($config->clientId())->toBe('legacy-client')
        ->and($config->isTrusted('legacy-client'))->toBeTrue()
        ->and($config->isTrusted('additional-client'))->toBeTrue();
});
