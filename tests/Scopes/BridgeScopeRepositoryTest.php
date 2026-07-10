<?php

declare(strict_types=1);

use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\Scope as BridgeScope;
use Laravel\Passport\Bridge\ScopeRepository as PassportBridgeScopeRepository;
use Laravel\Passport\Passport;

it('is bound over passport\'s bridge scope repository', function () {
    $resolved = app(PassportBridgeScopeRepository::class);

    expect(get_class($resolved))->toBe('Bambamboole\\LaravelOidc\\Scopes\\BridgeScopeRepository');
});

it('resolves oidc scopes that passport does not know', function () {
    $entity = app(PassportBridgeScopeRepository::class)->getScopeEntityByIdentifier('openid');

    expect($entity)->not->toBeNull()
        ->and($entity->getIdentifier())->toBe('openid');
});

it('returns null for unknown scopes', function () {
    expect(app(PassportBridgeScopeRepository::class)->getScopeEntityByIdentifier('nope'))->toBeNull();
});

it('finalizes scopes through the contract', function () {
    Passport::tokensCan(['project:update' => 'Update projects']);
    $client = new BridgeClient('client-id', 'Test', ['https://rp.test/callback']);

    $finalized = app(PassportBridgeScopeRepository::class)->finalizeScopes(
        [new BridgeScope('openid'), new BridgeScope('project:update'), new BridgeScope('nope')],
        'authorization_code',
        $client,
        '1',
    );

    expect(collect($finalized)->map->getIdentifier()->all())->toBe(['openid', 'project:update']);
});
