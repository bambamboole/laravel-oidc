<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Clients\FirstPartyClientProvisioningException;
use Bambamboole\LaravelOidc\Clients\FirstPartyClientProvisioningOutcome;
use Bambamboole\LaravelOidc\Clients\FirstPartyClientProvisioningResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;

it('prevents duplicate managed rows as the concurrency backstop', function () {
    expect(Schema::hasColumn('oauth_clients', 'oidc_provisioning_key'))->toBeTrue();

    $clients = app(ClientRepository::class);
    $first = $clients->createAuthorizationCodeGrantClient('One', ['https://one.test/callback']);
    $second = $clients->createAuthorizationCodeGrantClient('Two', ['https://two.test/callback']);

    $first->forceFill(['oidc_provisioning_key' => 'first-party'])->save();

    expect(fn () => $second->forceFill(['oidc_provisioning_key' => 'first-party'])->save())
        ->toThrow(QueryException::class);
});

it('carries the client, one-time secret, and outcome in a readonly result', function () {
    $client = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('First-party app', ['https://app.test/login/callback']);

    $result = new FirstPartyClientProvisioningResult(
        client: $client,
        clientId: (string) $client->getKey(),
        clientSecret: 'plain-secret',
        outcome: FirstPartyClientProvisioningOutcome::Created,
    );

    expect($result->client)->toBe($client)
        ->and($result->clientId)->toBe((string) $client->getKey())
        ->and($result->clientSecret)->toBe('plain-secret')
        ->and($result->outcome)->toBe(FirstPartyClientProvisioningOutcome::Created);
});

it('provides a constructible provisioning exception', function () {
    $exception = new FirstPartyClientProvisioningException('Provisioning failed.');

    expect($exception->getMessage())->toBe('Provisioning failed.')
        ->and((new ReflectionClass($exception))->isSubclassOf(RuntimeException::class))->toBeTrue();
});
