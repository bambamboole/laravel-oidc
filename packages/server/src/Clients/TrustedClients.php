<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Whether a client skips the consent prompt. Trust comes from configuration
 * (the first-party client and `oidc.trusted_clients`) or from the client's
 * own `trusted` column, which the administration API manages.
 */
final readonly class TrustedClients
{
    public function __construct(
        private FirstPartyClientConfig $config,
        private ClientRepository $clients,
    ) {}

    public function isTrusted(Client|string|int $client): bool
    {
        $clientId = $client instanceof Client ? (string) $client->getKey() : (string) $client;

        if ($this->config->isTrusted($clientId)) {
            return true;
        }

        $model = $client instanceof Client ? $client : $this->clients->findActive($clientId);

        return $model !== null && (bool) $model->getAttribute('trusted');
    }
}
