<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Bambamboole\LaravelOidc\Issuer;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use RuntimeException;

class IdTokenBuilder
{
    public function __construct(private readonly ClaimsResolver $claims) {}

    public function build(AccessTokenEntityInterface $accessToken, ?string $nonce, ?int $authTime): string
    {
        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText(PassportKeys::privateKey()),
            InMemory::plainText(PassportKeys::publicKey()),
        );

        $clientId = $accessToken->getClient()->getIdentifier();
        $scopes = array_map(
            fn (ScopeEntityInterface $scope) => $scope->getIdentifier(),
            $accessToken->getScopes(),
        );
        $now = new DateTimeImmutable;

        $builder = $config->builder()
            ->withHeader('kid', Jwk::fromPem(PassportKeys::publicKey())['kid'])
            ->issuedBy(Issuer::url())
            ->permittedFor($clientId)
            ->relatedTo((string) $accessToken->getUserIdentifier())
            ->issuedAt($now)
            ->expiresAt($now->modify('+'.config('oidc.id_token_ttl').' seconds'))
            ->withClaim('azp', $clientId)
            ->withClaim('at_hash', $this->atHash($accessToken->toString()));

        if ($nonce !== null && $nonce !== '') {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        if ($authTime !== null) {
            $builder = $builder->withClaim('auth_time', $authTime);
        }

        foreach ($this->claims->resolve($this->resolveUser($accessToken))->forScopes($scopes) as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    private function atHash(string $accessTokenJwt): string
    {
        $hash = substr(hash('sha256', $accessTokenJwt, true), 0, 16);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    private function resolveUser(AccessTokenEntityInterface $accessToken): Authenticatable
    {
        $guard = config('passport.guard', 'web');
        $provider = Auth::createUserProvider(config("auth.guards.{$guard}.provider"));
        $user = $provider?->retrieveById($accessToken->getUserIdentifier());

        return $user ?? throw new RuntimeException(
            'Unable to resolve the user for id_token issuance: '.$accessToken->getUserIdentifier(),
        );
    }
}
