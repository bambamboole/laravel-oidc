<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Exchange;

use Bambamboole\LaravelOidc\Contracts\ExchangePolicy;
use Bambamboole\LaravelOidc\Token\AccessTokenMinter;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use Bambamboole\LaravelOidc\Token\TokenInspector;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

class TokenExchanger
{
    private const string GRANT_URN = 'urn:ietf:params:oauth:grant-type:token-exchange';

    public function __construct(
        private readonly ExchangePolicy $policy,
        private readonly TokenInspector $inspector,
        private readonly AccessTokenMinter $minter,
        private readonly ScopeRepository $scopes,
    ) {}

    /**
     * @param  string[]  $scopes
     */
    public function exchange(
        string $subjectToken,
        Client $requestingClient,
        string $audience,
        array $scopes,
        ?DateInterval $accessTokenTTL = null,
    ): OidcAccessToken {
        $parsed = $this->inspector->parse($subjectToken);
        $dbToken = $this->inspector->accessToken($subjectToken);

        if ($parsed === null || $dbToken === null || (bool) $dbToken->getAttribute('revoked')) {
            throw OAuthServerException::invalidGrant('The subject token is invalid.');
        }

        if (((string) ($dbToken->getAttribute('user_id') ?? '')) === '') {
            throw OAuthServerException::invalidGrant('The subject token must be bound to a user.');
        }

        $claims = $parsed->claims()->all();
        $subjectExpiresAt = $this->claimTimestamp($claims['exp'] ?? null);

        if ($subjectExpiresAt <= time()) {
            throw OAuthServerException::invalidGrant('The subject token has expired.');
        }

        $dbExpiresAt = $dbToken->getAttribute('expires_at');
        if ($dbExpiresAt instanceof DateTimeInterface && $dbExpiresAt->getTimestamp() <= time()) {
            throw OAuthServerException::invalidGrant('The subject token has expired.');
        }

        $result = $this->policy->authorize(new ExchangeRequest(
            client: $requestingClient,
            subjectClaims: $claims,
            requestedAudience: $audience,
            requestedScopes: $scopes,
            subjectExpiresAt: $subjectExpiresAt,
        ));

        $bridgeClient = new BridgeClient((string) $requestingClient->getKey(), (string) $requestingClient->getAttribute('name'), [], true);
        $scopeIds = array_map(
            fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $this->scopes->finalizeScopes(
                array_values(array_filter(array_map(
                    fn (string $id) => $this->scopes->getScopeEntityByIdentifier($id),
                    $result->scopes,
                ))),
                self::GRANT_URN,
                $bridgeClient,
                $result->userId,
            ),
        );

        $ttl = $this->cappedTtl($accessTokenTTL ?? Passport::tokensExpireIn(), $result->expiresAt);

        $token = $this->minter->mint($result->userId, $requestingClient, $scopeIds, $ttl, $result->audience);
        $token->setGrantType(self::GRANT_URN);
        $token->setSubjectClaims($claims);
        $token->addExtraClaim('act', ['client_id' => (string) $requestingClient->getKey()]);

        return $token;
    }

    private function cappedTtl(DateInterval $default, int $subjectExpiresAt): DateInterval
    {
        $defaultExpiry = (new DateTimeImmutable)->add($default)->getTimestamp();
        $seconds = max(1, min($defaultExpiry, $subjectExpiresAt) - time());

        return new DateInterval('PT'.$seconds.'S');
    }

    private function claimTimestamp(mixed $exp): int
    {
        if ($exp instanceof DateTimeImmutable) {
            return $exp->getTimestamp();
        }

        return is_numeric($exp) ? (int) $exp : 0;
    }
}
