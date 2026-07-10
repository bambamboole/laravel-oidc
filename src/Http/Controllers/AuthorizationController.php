<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Contracts\ScopeRepository;
use Bambamboole\LaravelOidc\Scopes\Scope;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Http\Controllers\AuthorizationController as PassportAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;

class AuthorizationController extends PassportAuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        StatefulGuard $guard,
        ClientRepository $clients,
        protected ScopeRepository $scopeRepository,
    ) {
        parent::__construct($server, $guard, $clients);
    }

    protected function parseScopes(AuthorizationRequestInterface $authRequest): array
    {
        return collect($authRequest->getScopes())
            ->map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier())
            ->unique()
            ->map(fn (string $id): ?Scope => $this->scopeRepository->find($id))
            ->filter()
            ->values()
            ->all();
    }
}
