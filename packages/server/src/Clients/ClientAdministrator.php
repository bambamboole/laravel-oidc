<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Audit\Auditor;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * The write model behind the client administration API. It only ever sees
 * owner-less, non-revoked clients; clients carrying a provisioning key are
 * reconciled by their artisan command and stay read-only here.
 */
final readonly class ClientAdministrator
{
    public function __construct(
        private ClientRepository $clients,
        private ClientDefinitionValidator $validator,
        private Auditor $auditor,
    ) {}

    /** @return Builder<Client> */
    public function query(): Builder
    {
        return Passport::client()->newQuery()->where('revoked', false)->whereNull('owner_id');
    }

    public function find(string $clientId): ?Client
    {
        return $this->query()->whereKey($clientId)->first();
    }

    public function create(ClientDefinition $definition, ?string $actorClientId = null): AdministeredClient
    {
        $definition = $this->validator->validate($definition);

        $client = $this->transaction(function () use ($definition): Client {
            $client = Passport::client();
            $client->forceFill([
                'name' => $definition->name,
                'secret' => Str::random(40),
                'provider' => null,
                'revoked' => false,
                ...$this->definitionAttributes($definition),
            ])->save();

            return $client;
        });

        $this->auditor->log(AuditEventType::ClientCreated, clientId: (string) $client->getKey(), context: [
            'actor' => $actorClientId ?? 'console',
            'name' => $definition->name,
            'grant_types' => $definition->grantTypes,
            'scopes' => $definition->scopes,
            'redirect_uris' => $definition->redirectUris,
            'trusted' => $definition->trusted,
        ]);

        return new AdministeredClient($client->refresh(), $client->plainSecret);
    }

    public function update(Client $client, ClientDefinition $definition, ?string $actorClientId = null): Client
    {
        $this->assertUnmanaged($client);
        $definition = $this->validator->validate($definition, $client->confidential());

        $changed = $this->transaction(function () use ($client, $definition): array {
            $locked = $this->lock($client);
            $before = ClientDefinition::fromClient($locked);
            $locked->forceFill(['name' => $definition->name, ...$this->definitionAttributes($definition)])->save();

            return $before->changedFields($definition);
        });

        $this->auditor->log(AuditEventType::ClientUpdated, clientId: (string) $client->getKey(), context: [
            'actor' => $actorClientId ?? 'console',
            'changed' => $changed,
        ]);

        return $client->refresh();
    }

    public function rotateSecret(Client $client, ?string $actorClientId = null): AdministeredClient
    {
        if (! $client->confidential()) {
            throw ClientAdministrationException::publicClient();
        }

        $rotated = $this->transaction(function () use ($client): Client {
            $locked = $this->lock($client);
            $this->clients->regenerateSecret($locked);

            return $locked;
        });

        $this->auditor->log(AuditEventType::ClientSecretRotated, clientId: (string) $client->getKey(), context: [
            'actor' => $actorClientId ?? 'console',
        ]);

        return new AdministeredClient($rotated, $rotated->plainSecret);
    }

    public function revoke(Client $client, ?string $actorClientId = null): void
    {
        $this->assertUnmanaged($client);

        if ($actorClientId !== null && $actorClientId === (string) $client->getKey()) {
            throw ClientAdministrationException::selfRevocation();
        }

        $this->transaction(fn () => $this->clients->delete($this->lock($client)));

        $this->auditor->log(AuditEventType::ClientRevoked, clientId: (string) $client->getKey(), context: [
            'actor' => $actorClientId ?? 'console',
            'name' => (string) $client->getAttribute('name'),
        ]);
    }

    /** @return array<string, mixed> */
    private function definitionAttributes(ClientDefinition $definition): array
    {
        return [
            'redirect_uris' => $definition->redirectUris,
            'grant_types' => $definition->grantTypes,
            'scopes' => $definition->scopes,
            'post_logout_redirect_uris' => json_encode($definition->postLogoutRedirectUris, JSON_THROW_ON_ERROR),
            'allowed_exchange_audiences' => json_encode($definition->allowedExchangeAudiences, JSON_THROW_ON_ERROR),
            'backchannel_logout_uri' => $definition->backchannelLogoutUri,
            'backchannel_logout_session_required' => $definition->backchannelLogoutSessionRequired,
            'trusted' => $definition->trusted,
        ];
    }

    private function assertUnmanaged(Client $client): void
    {
        if ($client->getRawOriginal('oidc_provisioning_key') !== null) {
            throw ClientAdministrationException::managedClient();
        }
    }

    private function lock(Client $client): Client
    {
        $locked = $this->query()->whereKey($client->getKey())->lockForUpdate()->first();

        return $locked ?? throw ClientAdministrationException::revoked();
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function transaction(Closure $callback): mixed
    {
        $connection = config('passport.connection');

        return DB::connection(is_string($connection) ? $connection : null)->transaction($callback);
    }
}
