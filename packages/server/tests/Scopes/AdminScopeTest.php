<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Contracts\ScopeRepository;
use Bambamboole\LaravelOidc\Server\Scopes\AdminScope;
use Bambamboole\LaravelOidc\Server\Scopes\Scope;
use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\Scope as BridgeScope;
use Laravel\Passport\Bridge\ScopeRepository as PassportBridgeScopeRepository;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/** @param list<string>|null $scopes */
function adminCapableClient(?array $scopes = ['oidc:admin']): Client
{
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient('IaC runner');
    $client->forceFill(['scopes' => $scopes])->save();

    return $client;
}

/** @return list<string> */
function finalizedScopeIds(Client $client, string $grantType, string ...$requested): array
{
    $finalized = app(PassportBridgeScopeRepository::class)->finalizeScopes(
        array_map(fn (string $id): BridgeScope => new BridgeScope($id), $requested),
        $grantType,
        new BridgeClient((string) $client->getKey(), (string) $client->getAttribute('name'), [], $client->confidential()),
    );

    return collect($finalized)->map->getIdentifier()->values()->all();
}

it('is unknown while administration is disabled', function () {
    expect(app(ScopeRepository::class)->find('oidc:admin'))->toBeNull();
});

it('is registered hidden while administration is enabled', function () {
    config(['oidc.admin.enabled' => true]);

    $scope = app(ScopeRepository::class)->find('oidc:admin');

    expect($scope)->toBeInstanceOf(Scope::class)
        ->and($scope->hidden)->toBeTrue()
        ->and($this->getJson('/.well-known/openid-configuration')->json('scopes_supported'))->not->toContain('oidc:admin');
});

it('honours the configured scope name', function () {
    config(['oidc.admin.enabled' => true, 'oidc.admin.scope' => 'clients:manage']);

    expect(AdminScope::id())->toBe('clients:manage')
        ->and(app(ScopeRepository::class)->find('clients:manage'))->toBeInstanceOf(Scope::class)
        ->and(app(ScopeRepository::class)->find('oidc:admin'))->toBeNull();
});

it('is issued to a confidential client that lists it for client credentials', function () {
    config(['oidc.admin.enabled' => true]);

    expect(finalizedScopeIds(adminCapableClient(), 'client_credentials', 'oidc:admin'))->toBe(['oidc:admin']);
});

it('is never issued through the unrestricted scopes column', function () {
    config(['oidc.admin.enabled' => true]);

    expect(finalizedScopeIds(adminCapableClient(scopes: null), 'client_credentials', 'oidc:admin'))->toBe([]);
});

it('is never issued through the wildcard scope', function () {
    config(['oidc.admin.enabled' => true]);

    expect(finalizedScopeIds(adminCapableClient(scopes: ['*']), 'client_credentials', '*', 'oidc:admin'))->toBe(['*']);
});

it('is never issued for interactive grants', function (string $grantType) {
    config(['oidc.admin.enabled' => true]);

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Web', ['https://rp.test/cb']);
    $client->forceFill(['scopes' => ['openid', 'oidc:admin']])->save();

    expect(finalizedScopeIds($client, $grantType, 'openid', 'oidc:admin'))->toBe(['openid']);
})->with(['authorization_code', 'refresh_token', 'urn:ietf:params:oauth:grant-type:token-exchange']);

it('lands in a client credentials token only for an explicitly listed client', function () {
    config(['oidc.admin.enabled' => true]);

    $listed = adminCapableClient();
    $unrestricted = adminCapableClient(scopes: null);

    $token = fn (Client $client) => parseAccessToken((string) $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'scope' => 'oidc:admin',
    ])->assertOk()->json('access_token'))->claims()->get('scopes');

    expect($token($listed))->toBe(['oidc:admin'])
        ->and($token($unrestricted))->toBe([]);
});

it('is rejected at the token endpoint while administration is disabled', function () {
    $client = adminCapableClient();

    $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'scope' => 'oidc:admin',
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
});
