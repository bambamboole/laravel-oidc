<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Grant;

use Bambamboole\LaravelOidc\Contracts\ExchangePolicy;
use Bambamboole\LaravelOidc\Exchange\ExchangeRequest;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use Bambamboole\LaravelOidc\Token\TokenInspector;
use DateInterval;
use DateTimeImmutable;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

class TokenExchangeGrant extends AbstractGrant
{
    private const string GRANT_URN = 'urn:ietf:params:oauth:grant-type:token-exchange';

    private const string ACCESS_TOKEN_URN = 'urn:ietf:params:oauth:token-type:access_token';

    public function __construct(
        private readonly ExchangePolicy $policy,
        private readonly TokenInspector $inspector,
    ) {}

    public function getIdentifier(): string
    {
        return self::GRANT_URN;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);

        if (! $client->isConfidential()) {
            throw OAuthServerException::invalidClient($request);
        }

        if ($this->getRequestParameter('subject_token_type', $request) !== self::ACCESS_TOKEN_URN) {
            throw OAuthServerException::invalidRequest('subject_token_type', 'Only access_token subject tokens are supported.');
        }

        if ($this->getRequestParameter('requested_token_type', $request, self::ACCESS_TOKEN_URN) !== self::ACCESS_TOKEN_URN) {
            throw OAuthServerException::invalidRequest('requested_token_type', 'Only access_token may be requested.');
        }

        $subjectTokenJwt = $this->getRequestParameter('subject_token', $request);
        if ($subjectTokenJwt === null) {
            throw OAuthServerException::invalidRequest('subject_token');
        }

        $parsed = $this->inspector->parse($subjectTokenJwt);
        $dbToken = $this->inspector->accessToken($subjectTokenJwt);
        if ($parsed === null || $dbToken === null || (bool) $dbToken->getAttribute('revoked')) {
            throw OAuthServerException::invalidGrant('The subject token is invalid.');
        }

        $claims = $parsed->claims()->all();

        $passportClient = Passport::client()->newQuery()->find($client->getIdentifier());
        if ($passportClient === null) {
            throw OAuthServerException::invalidClient($request);
        }

        $result = $this->policy->authorize(new ExchangeRequest(
            client: $passportClient,
            subjectClaims: $claims,
            requestedAudience: $this->getRequestParameter('audience', $request),
            requestedScopes: $this->scopeParam($request),
            subjectExpiresAt: $this->claimTimestamp($claims['exp'] ?? null),
        ));

        $scopeEntities = array_map(
            fn (string $id) => $this->scopeRepository->getScopeEntityByIdentifier($id),
            $result->scopes,
        );
        $scopeEntities = $this->scopeRepository->finalizeScopes(
            array_values(array_filter($scopeEntities)),
            $this->getIdentifier(),
            $client,
            $result->userId,
        );

        $accessToken = $this->issueAccessToken(
            $this->cappedTtl($accessTokenTTL, $result->expiresAt),
            $client,
            $result->userId,
            $scopeEntities,
        );

        // OidcAccessToken (Passport::useAccessTokenEntity) is what league returns here; the RFC 8693
        // `act` claim is server-trusted, so it bypasses the user-driven hook blocklist via addExtraClaim.
        if ($accessToken instanceof OidcAccessToken) {
            $accessToken->setGrantType(self::GRANT_URN);
            $accessToken->setAudience(...$result->audience);
            $accessToken->setSubjectClaims($claims);
            $accessToken->addExtraClaim('act', ['client_id' => $client->getIdentifier()]);
        }

        $this->getEmitter()->emit(new RequestEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request));
        $responseType->setAccessToken($accessToken);

        // Exchange must never mint a refresh token; issueRefreshToken is deliberately never called.
        return $responseType;
    }

    private function claimTimestamp(mixed $exp): int
    {
        if ($exp instanceof DateTimeImmutable) {
            return $exp->getTimestamp();
        }

        return is_numeric($exp) ? (int) $exp : 0;
    }

    private function cappedTtl(DateInterval $default, int $subjectExpiresAt): DateInterval
    {
        $defaultExpiry = (new DateTimeImmutable)->add($default)->getTimestamp();
        $expiry = min($defaultExpiry, $subjectExpiresAt);
        $seconds = max(1, $expiry - (new DateTimeImmutable)->getTimestamp());

        return new DateInterval('PT'.$seconds.'S');
    }

    /** @return string[]|null */
    private function scopeParam(ServerRequestInterface $request): ?array
    {
        $scope = $this->getRequestParameter('scope', $request);

        return $scope === null ? null : array_values(array_filter(explode(' ', $scope)));
    }
}
