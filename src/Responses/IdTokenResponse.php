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
        $scopes = array_map(
            fn (ScopeEntityInterface $scope) => $scope->getIdentifier(),
            $accessToken->getScopes(),
        );

        if (! in_array('openid', $scopes, true) || $accessToken->getUserIdentifier() === null) {
            return [];
        }

        return ['id_token' => $this->builder->build($accessToken, $this->nonce, $this->authTime)];
    }
}
