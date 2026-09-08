<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Audit\Auditor;
use Bambamboole\LaravelOidc\Server\Scopes\AdminScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * Bootstraps the package-managed administration client: a confidential,
 * owner-less client_credentials client that lists the admin scope. It is
 * identified by the `admin` provisioning key, so re-running reconciles the
 * existing client instead of creating a second one. Further admin clients
 * are created through the API itself.
 */
final readonly class AdminClientProvisioner
{
    private const ProvisioningKey = 'admin';

    public function __construct(
        private ClientAdministrator $administrator,
        private Auditor $auditor,
    ) {}

    public function provision(string $name, bool $rotateSecret = false): AdminClientProvisioningResult
    {
        if (! config('oidc.admin.enabled', false)) {
            throw ClientAdministrationException::administrationDisabled();
        }

        $name = trim($name);

        if ($name === '') {
            throw new ClientAdministrationException('invalid_name', 'The admin client name must not be empty.');
        }

        try {
            return $this->recordProvisioned($this->transactionalProvision($name, $rotateSecret));
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraint($exception) && $this->managedClient(lock: false) !== null) {
                return $this->recordProvisioned($this->transactionalProvision($name, $rotateSecret));
            }

            throw $exception;
        }
    }

    private function transactionalProvision(string $name, bool $rotateSecret): AdminClientProvisioningResult
    {
        $connection = config('passport.connection');

        return DB::connection(is_string($connection) ? $connection : null)->transaction(function () use ($name, $rotateSecret): AdminClientProvisioningResult {
            $client = $this->managedClient(lock: true);
            $created = false;
            $secret = null;

            if ($client === null) {
                $administered = $this->administrator->create(new ClientDefinition(
                    name: $name,
                    grantTypes: ['client_credentials'],
                    scopes: [AdminScope::id()],
                ));
                $client = $administered->client;
                $client->forceFill(['oidc_provisioning_key' => self::ProvisioningKey])->save();
                $secret = $administered->plainSecret;
                $created = true;
            } else {
                $this->assertEligible($client);
                $client->forceFill([
                    'name' => $name,
                    'grant_types' => ['client_credentials'],
                    'scopes' => [AdminScope::id()],
                ])->save();
            }

            if ($rotateSecret) {
                $secret = $this->administrator->rotateSecret($client)->plainSecret;
            }

            return new AdminClientProvisioningResult(
                client: $client->refresh(),
                clientId: (string) $client->getKey(),
                clientSecret: $secret,
                wasCreated: $created,
                secretRotated: $rotateSecret,
            );
        });
    }

    private function managedClient(bool $lock): ?Client
    {
        $query = Passport::client()->newQuery()->where('oidc_provisioning_key', self::ProvisioningKey);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function assertEligible(Client $client): void
    {
        if ($client->getAttribute('revoked') === true) {
            throw ClientAdministrationException::revoked();
        }

        if (! $client->confidential() || ! $client->firstParty()) {
            throw new ClientAdministrationException('ineligible', 'The admin client must be a confidential client that is not owned by a user.');
        }
    }

    private function recordProvisioned(AdminClientProvisioningResult $result): AdminClientProvisioningResult
    {
        $this->auditor->log(AuditEventType::ClientProvisioned, clientId: $result->clientId, context: [
            'provisioning_key' => self::ProvisioningKey,
            'created' => $result->wasCreated,
            'secret_rotated' => $result->secretRotated,
        ]);

        return $result;
    }

    private function isUniqueConstraint(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return is_string($sqlState) && in_array($sqlState, ['23000', '23505'], true);
    }
}
