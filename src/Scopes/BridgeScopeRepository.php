<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Scopes;

use Bambamboole\LaravelOidc\Contracts\ScopeRepository as ScopeRepositoryContract;
use Illuminate\Support\Collection;
use Laravel\Passport\Bridge\Scope as BridgeScope;
use Laravel\Passport\Bridge\ScopeRepository as PassportBridgeScopeRepository;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class BridgeScopeRepository extends PassportBridgeScopeRepository
{
    public function __construct(
        ClientRepository $clients,
        private readonly ScopeRepositoryContract $scopes,
    ) {
        parent::__construct($clients);
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return $this->scopes->find($identifier) instanceof Scope ? new BridgeScope($identifier) : null;
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        $candidates = collect($scopes)
            ->unless(in_array($grantType, ['password', 'personal_access', 'client_credentials']),
                fn (Collection $scopes): Collection => $scopes->reject(
                    fn (ScopeEntityInterface $scope): bool => $scope->getIdentifier() === '*'
                )
            )
            ->map(fn (ScopeEntityInterface $scope): ?Scope => $this->scopes->find($scope->getIdentifier()))
            ->filter()
            ->when($this->clients->findActive($clientEntity->getIdentifier()),
                fn (Collection $scopes, Client $client): Collection => $scopes->filter(
                    fn (Scope $scope): bool => $client->hasScope($scope->id)
                )
            )
            ->values()
            ->all();

        return collect($this->scopes->finalize($candidates, $grantType, $clientEntity, $userIdentifier))
            ->map(fn (Scope $scope): ScopeEntityInterface => new BridgeScope($scope->id))
            ->values()
            ->all();
    }
}
