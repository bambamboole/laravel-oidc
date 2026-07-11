<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Responses;

use Bambamboole\LaravelOidc\Token\IdTokenBuilder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

class IdTokenResponse extends BearerTokenResponse
{
    private ?string $nonce = null;

    private ?int $authTime = null;

    public function __construct(private readonly IdTokenBuilder $builder) {}

    public function setNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }

    public function setAuthTime(?int $authTime): void
    {
        $this->authTime = $authTime;
    }

    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        // Read-and-clear: this response type is resolved once and reused across
        // requests on long-lived workers (Octane), so nonce/auth_time must never
        // leak into a subsequent token issuance.
        $nonce = $this->nonce;
        $authTime = $this->authTime;
        $this->nonce = null;
        $this->authTime = null;

        $scopes = array_map(
            fn (ScopeEntityInterface $scope) => $scope->getIdentifier(),
            $accessToken->getScopes(),
        );

        if (! in_array('openid', $scopes, true) || $accessToken->getUserIdentifier() === null) {
            return [];
        }

        return ['id_token' => $this->builder->build($accessToken, $nonce, $authTime, $this->requestGrantType())];
    }

    private function requestGrantType(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $value = app('request')->input('grant_type');

        return is_string($value) ? $value : null;
    }
}
