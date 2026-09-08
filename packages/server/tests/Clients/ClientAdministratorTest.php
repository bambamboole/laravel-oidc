<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Audit\AuditEvent;
use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Clients\ClientAdministrationException;
use Bambamboole\LaravelOidc\Server\Clients\ClientAdministrator;
use Bambamboole\LaravelOidc\Server\Clients\ClientDefinition;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

function administrator(): ClientAdministrator
{
    return app(ClientAdministrator::class);
}

/** @param array<string, mixed> $overrides */
function webClientDefinition(array $overrides = []): ClientDefinition
{
    return (new ClientDefinition('Orders Web', ['https://orders.test/callback']))->with($overrides);
}

/** @return array{class-string<ClientAdministrationException>, string} */
function administrationFailure(string $reason): array
{
    $exception = match ($reason) {
        'managed_client' => ClientAdministrationException::managedClient(),
        'self_revocation' => ClientAdministrationException::selfRevocation(),
        'public_client' => ClientAdministrationException::publicClient(),
        'revoked' => ClientAdministrationException::revoked(),
        default => throw new InvalidArgumentException("Unknown administration failure [{$reason}]."),
    };

    expect($exception->reason)->toBe($reason);

    return [ClientAdministrationException::class, $exception->getMessage()];
}

it('creates a confidential owner-less client and returns the secret once', function () {
    $created = administrator()->create(webClientDefinition([
        'redirectUris' => [' https://orders.test/callback ', 'https://orders.test/callback', 'https://orders.test/other'],
        'postLogoutRedirectUris' => ['https://orders.test/'],
        'backchannelLogoutUri' => 'https://orders.test/backchannel',
        'backchannelLogoutSessionRequired' => true,
        'allowedExchangeAudiences' => ['https://api.orders.test'],
        'grantTypes' => ['authorization_code', 'refresh_token', ClientDefinition::TokenExchangeGrant],
        'scopes' => ['openid', 'email', 'openid'],
        'trusted' => true,
    ]));

    $client = $created->client;

    expect($created->plainSecret)->toBeString()->toHaveLength(40)
        ->and(Hash::check($created->plainSecret, (string) $client->getRawOriginal('secret')))->toBeTrue()
        ->and($client->confidential())->toBeTrue()
        ->and($client->firstParty())->toBeTrue()
        ->and($client->getAttribute('redirect_uris'))->toBe(['https://orders.test/callback', 'https://orders.test/other'])
        ->and($client->getAttribute('grant_types'))->toBe(['authorization_code', 'refresh_token', ClientDefinition::TokenExchangeGrant])
        ->and($client->getAttribute('scopes'))->toBe(['openid', 'email'])
        ->and($client->getRawOriginal('post_logout_redirect_uris'))->toBe('["https:\/\/orders.test\/"]')
        ->and($client->getRawOriginal('allowed_exchange_audiences'))->toBe('["https:\/\/api.orders.test"]')
        ->and($client->getAttribute('backchannel_logout_uri'))->toBe('https://orders.test/backchannel')
        ->and((bool) $client->getAttribute('backchannel_logout_session_required'))->toBeTrue()
        ->and((bool) $client->getAttribute('trusted'))->toBeTrue()
        ->and(ClientDefinition::fromClient($client)->redirectUris)->toBe(['https://orders.test/callback', 'https://orders.test/other']);
});

it('distinguishes an unrestricted scopes column from an empty scope list', function () {
    $unrestricted = administrator()->create(webClientDefinition())->client;
    $none = administrator()->create(webClientDefinition(['scopes' => []]))->client;

    expect($unrestricted->getAttribute('scopes'))->toBeNull()
        ->and($unrestricted->hasScope('openid'))->toBeTrue()
        ->and($none->getAttribute('scopes'))->toBe([])
        ->and($none->hasScope('openid'))->toBeFalse()
        ->and(ClientDefinition::fromClient($none)->scopes)->toBe([]);
});

it('rejects invalid definitions with field-level errors', function (array $overrides, string $field) {
    try {
        administrator()->create(webClientDefinition($overrides));
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    $this->fail('Expected a validation exception.');
})->with([
    'blank name' => [['name' => '  '], 'client_name'],
    'long name' => [['name' => str_repeat('a', 256)], 'client_name'],
    'no grant types' => [['grantTypes' => []], 'grant_types'],
    'unsupported grant' => [['grantTypes' => ['password']], 'grant_types'],
    'refresh without code' => [['grantTypes' => ['refresh_token']], 'grant_types'],
    'code without redirect' => [['redirectUris' => []], 'redirect_uris'],
    'redirect with fragment' => [['redirectUris' => ['https://orders.test/cb#x']], 'redirect_uris'],
    'relative post-logout uri' => [['postLogoutRedirectUris' => ['/bye']], 'post_logout_redirect_uris'],
    'relative backchannel uri' => [['backchannelLogoutUri' => '/logout'], 'backchannel_logout_uri'],
    'exchange without audience' => [['grantTypes' => ['authorization_code', ClientDefinition::TokenExchangeGrant]], 'allowed_exchange_audiences'],
    'malformed audience' => [['allowedExchangeAudiences' => ['not an audience']], 'allowed_exchange_audiences'],
    'unknown scope' => [['scopes' => ['nope']], 'scopes'],
    'wildcard scope' => [['scopes' => ['*']], 'scopes'],
]);

it('rejects audiences while token exchange is disabled', function () {
    config(['oidc.token_exchange.enabled' => false]);

    expect(fn () => administrator()->create(webClientDefinition(['allowedExchangeAudiences' => ['https://api.orders.test']])))
        ->toThrow(ValidationException::class);
});

it('only assigns the administration scope to a client credentials client', function () {
    config(['oidc.admin.enabled' => true]);

    expect(fn () => administrator()->create(webClientDefinition(['scopes' => ['oidc:admin']])))
        ->toThrow(ValidationException::class);

    $admin = administrator()->create(new ClientDefinition('Runner', grantTypes: ['client_credentials'], scopes: ['oidc:admin']))->client;

    expect($admin->getAttribute('scopes'))->toBe(['oidc:admin']);
});

it('updates the definition while keeping the secret', function () {
    $created = administrator()->create(webClientDefinition());
    $definition = ClientDefinition::fromClient($created->client)->with(['name' => 'Orders Web v2', 'trusted' => true]);

    $updated = administrator()->update($created->client, $definition);

    expect($updated->getAttribute('name'))->toBe('Orders Web v2')
        ->and((bool) $updated->getAttribute('trusted'))->toBeTrue()
        ->and(Hash::check($created->plainSecret, (string) $updated->getRawOriginal('secret')))->toBeTrue()
        ->and(ClientDefinition::fromClient($updated))->toEqual($definition);
});

it('refuses to modify a client managed by an artisan command', function () {
    $client = administrator()->create(webClientDefinition())->client;
    $client->forceFill(['oidc_provisioning_key' => 'first-party'])->save();

    expect(fn () => administrator()->update($client, ClientDefinition::fromClient($client)))
        ->toThrow(...administrationFailure('managed_client'))
        ->and(fn () => administrator()->revoke($client))
        ->toThrow(...administrationFailure('managed_client'));
});

it('keeps a public client public and refuses secret-only features on it', function () {
    $public = app(ClientRepository::class)->createAuthorizationCodeGrantClient('MCP', ['https://mcp.test/cb'], confidential: false);

    $updated = administrator()->update($public, ClientDefinition::fromClient($public)->with(['name' => 'MCP client']));

    expect($updated->confidential())->toBeFalse()
        ->and($updated->getAttribute('name'))->toBe('MCP client')
        ->and(fn () => administrator()->update($public, ClientDefinition::fromClient($public)->with(['trusted' => true])))
        ->toThrow(ValidationException::class)
        ->and(fn () => administrator()->rotateSecret($public))
        ->toThrow(...administrationFailure('public_client'));
});

it('rotates the secret without revoking issued tokens', function () {
    $created = administrator()->create(webClientDefinition());
    $token = Passport::token()->forceCreate([
        'id' => 'token-1', 'user_id' => null, 'client_id' => $created->client->getKey(), 'scopes' => [], 'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $rotated = administrator()->rotateSecret($created->client);

    expect($rotated->plainSecret)->not->toBe($created->plainSecret)
        ->and(Hash::check($rotated->plainSecret, (string) $rotated->client->getRawOriginal('secret')))->toBeTrue()
        ->and(Hash::check($created->plainSecret, (string) $rotated->client->getRawOriginal('secret')))->toBeFalse()
        ->and($token->refresh()->getAttribute('revoked'))->toBeFalse();
});

it('revokes a client together with its tokens and hides it afterwards', function () {
    $client = administrator()->create(webClientDefinition())->client;
    $token = Passport::token()->forceCreate([
        'id' => 'token-2', 'user_id' => null, 'client_id' => $client->getKey(), 'scopes' => [], 'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    administrator()->revoke($client, actorClientId: 'someone-else');

    expect($client->refresh()->getAttribute('revoked'))->toBeTrue()
        ->and($token->refresh()->getAttribute('revoked'))->toBeTrue()
        ->and(administrator()->find((string) $client->getKey()))->toBeNull()
        ->and(fn () => administrator()->update($client, ClientDefinition::fromClient($client)))
        ->toThrow(...administrationFailure('revoked'));
});

it('refuses self revocation', function () {
    $client = administrator()->create(webClientDefinition())->client;

    expect(fn () => administrator()->revoke($client, actorClientId: (string) $client->getKey()))
        ->toThrow(...administrationFailure('self_revocation'))
        ->and($client->refresh()->getAttribute('revoked'))->toBeFalse();
});

it('does not expose user-owned clients', function () {
    $owned = Passport::client()->forceFill([
        'name' => 'Personal', 'secret' => 'x', 'redirect_uris' => ['https://rp.test/cb'], 'grant_types' => ['authorization_code'],
        'revoked' => false, 'owner_id' => 'user-1', 'owner_type' => 'users',
    ]);
    $owned->save();

    expect(administrator()->find((string) $owned->getKey()))->toBeNull()
        ->and(administrator()->query()->count())->toBe(0);
});

it('audits every administrative change with its actor', function () {
    $sink = fakeAudit();

    $created = administrator()->create(webClientDefinition(), actorClientId: 'runner');
    administrator()->update($created->client, ClientDefinition::fromClient($created->client)->with(['name' => 'Renamed']), actorClientId: 'runner');
    administrator()->rotateSecret($created->client, actorClientId: 'runner');
    administrator()->revoke($created->client, actorClientId: 'runner');

    $clientId = (string) $created->client->getKey();

    $sink->assertRecorded(AuditEventType::ClientCreated, fn (AuditEvent $event): bool => $event->clientId === $clientId
        && $event->context['actor'] === 'runner' && $event->context['name'] === 'Orders Web');
    $sink->assertRecorded(AuditEventType::ClientUpdated, fn (AuditEvent $event): bool => $event->clientId === $clientId
        && $event->context['changed'] === ['client_name']);
    $sink->assertRecorded(AuditEventType::ClientSecretRotated, fn (AuditEvent $event): bool => $event->clientId === $clientId
        && ! array_key_exists('secret', $event->context));
    $sink->assertRecorded(AuditEventType::ClientRevoked, fn (AuditEvent $event): bool => $event->clientId === $clientId
        && $event->context['actor'] === 'runner');
});
