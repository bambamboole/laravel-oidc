<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Clients\FirstPartyClientProvisioningOutcome;
use Bambamboole\LaravelOidc\Facades\Oidc;

it('provisions through the public facade', function () {
    $result = Oidc::provisionFirstPartyClient(
        name: 'First-party app',
        redirectUris: ['https://app.test/login/callback'],
        postLogoutRedirectUris: ['https://app.test'],
        allowedExchangeAudiences: ['https://api.test/orders'],
    );

    expect($result->outcome)->toBe(FirstPartyClientProvisioningOutcome::Created)
        ->and($result->clientId)->toBe((string) $result->client->getKey())
        ->and($result->clientSecret)->toBeString();
});
