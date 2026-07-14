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

it('reconciles a verified client credential through the public facade', function () {
    $created = Oidc::provisionFirstPartyClient(
        name: 'Old name',
        redirectUris: ['https://old.test/login/callback'],
    );

    $result = Oidc::provisionFirstPartyClient(
        name: 'New name',
        redirectUris: ['https://new.test/login/callback'],
        existingClientSecret: $created->clientSecret,
    );

    expect($result->outcome)->toBe(FirstPartyClientProvisioningOutcome::Reconciled)
        ->and($result->clientId)->toBe($created->clientId)
        ->and($result->clientSecret)->toBe($created->clientSecret);
});
