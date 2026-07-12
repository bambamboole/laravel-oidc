<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Clients;

final readonly class FirstPartyClientConfig
{
    /** @param string[] $additionalTrustedClientIds */
    private function __construct(
        private ?string $resolvedClientId,
        private bool $firstPartyTrusted,
        private array $additionalTrustedClientIds,
    ) {}

    public static function fromConfig(): self
    {
        $newClientId = config('oidc.first_party.client_id');
        $usesNewConfig = is_string($newClientId) && $newClientId !== '';
        $legacyClientId = config('oidc.first_party_client');
        $resolved = $usesNewConfig
            ? $newClientId
            : (is_string($legacyClientId) && $legacyClientId !== '' ? $legacyClientId : null);
        $trusted = array_values(array_unique(array_map(
            strval(...),
            (array) config('oidc.trusted_clients', []),
        )));

        return new self(
            resolvedClientId: $resolved,
            firstPartyTrusted: $usesNewConfig
                ? (bool) config('oidc.first_party.trusted', false)
                : ($resolved !== null && in_array($resolved, $trusted, true)),
            additionalTrustedClientIds: $trusted,
        );
    }

    public function clientId(): ?string
    {
        return $this->resolvedClientId;
    }

    public function isConfigured(): bool
    {
        return $this->resolvedClientId !== null;
    }

    public function isTrusted(string|int $clientId): bool
    {
        $clientId = (string) $clientId;

        return ($this->resolvedClientId === $clientId && $this->firstPartyTrusted)
            || in_array($clientId, $this->additionalTrustedClientIds, true);
    }
}
