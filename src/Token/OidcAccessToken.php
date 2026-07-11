<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use Bambamboole\LaravelOidc\Hooks\AccessTokenHookRunner;
use Bambamboole\LaravelOidc\Issuer;
use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

/**
 * Emits an RFC 9068 (application/at+jwt) access token. league's AccessTokenTrait::convertToJWT is
 * private and stops at a bare JWT (no iss, no typ, a non-standard `scopes` array); we re-use the
 * trait to redefine it. The legacy `scopes` array is retained because Passport's auth:api guard and
 * this package's userinfo read it — dropping it breaks authentication. toString() stays memoized:
 * league 9.4 + lcobucci 5.6 mint fresh microsecond iat/nbf per call, so the at_hash computed in
 * IdTokenBuilder must hash the identical string returned as access_token.
 */
class OidcAccessToken extends AccessToken
{
    use AccessTokenTrait;

    private ?string $serialized = null;

    /** @var string[] */
    private array $audience = [];

    private ?string $grantType = null;

    /** @var array<string, mixed> */
    private array $subjectClaims = [];

    /** @var array<string, mixed> */
    private array $extra = [];

    public function setAudience(string ...$audience): void
    {
        $this->audience = $audience;
    }

    public function setGrantType(?string $grantType): void
    {
        $this->grantType = $grantType;
    }

    public function getGrantType(): ?string
    {
        return $this->grantType;
    }

    public function exchangeAudience(): string
    {
        return $this->audience[0] ?? $this->getClient()->getIdentifier();
    }

    /** @param array<string, mixed> $claims */
    public function setSubjectClaims(array $claims): void
    {
        $this->subjectClaims = $claims;
    }

    /** @return array<string, mixed> */
    public function subjectClaims(): array
    {
        return $this->subjectClaims;
    }

    public function addExtraClaim(string $name, mixed $value): void
    {
        $this->extra[$name] = $value;
    }

    public function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $clientId = $this->getClient()->getIdentifier();
        $audience = $this->audience !== [] ? $this->audience : [$clientId];
        $scopeIds = array_map(
            fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $this->getScopes(),
        );
        $now = new DateTimeImmutable;

        $builder = $this->jwtConfiguration->builder()
            ->withHeader('typ', 'at+jwt')
            ->withHeader('kid', Jwk::fromPem(PassportKeys::publicKey())['kid'])
            ->issuedBy(Issuer::url())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getSubjectIdentifier())
            ->withClaim('client_id', $clientId)
            ->withClaim('scope', implode(' ', $scopeIds))
            ->withClaim('scopes', $scopeIds);

        foreach ($audience as $aud) {
            $builder = $builder->permittedFor($aud);
        }

        foreach (app(AccessTokenHookRunner::class)->claims($this) as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        foreach ($this->extra as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }

    public function toString(): string
    {
        return $this->serialized ??= $this->convertToJWT()->toString();
    }
}
