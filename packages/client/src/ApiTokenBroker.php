<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client;

use Bambamboole\LaravelOidc\Client\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Illuminate\Http\Client\Factory as Http;

class ApiTokenBroker
{
    private const string EXCHANGE_GRANT = 'urn:ietf:params:oauth:grant-type:token-exchange';

    private const string ACCESS_TOKEN_TYPE = 'urn:ietf:params:oauth:token-type:access_token';

    private const int EXPIRY_SKEW = 30;

    public function __construct(
        private readonly OidcDiscovery $discovery,
        private readonly Http $http,
    ) {}

    /**
     * @param  array<string, string>  $parameters
     */
    public function accessToken(array $parameters = [], ?string $audience = null): string
    {
        $audience ??= (string) config('oidc-client.issuer');
        $key = $this->cacheKey($audience, $parameters);
        $cached = session('oidc-client.exchanged.'.$key);

        if (is_array($cached) && is_string($cached['access_token'] ?? null) && (int) ($cached['expires_at'] ?? 0) > time() + self::EXPIRY_SKEW) {
            return $cached['access_token'];
        }

        $issued = $this->exchange($this->subjectToken(), $audience, $parameters);
        session()->put('oidc-client.exchanged.'.$key, $issued);

        return $issued['access_token'];
    }

    public function forget(): void
    {
        session()->forget('oidc-client.exchanged');
    }

    private function subjectToken(): string
    {
        $tokens = (array) session('oidc-client.tokens', []);
        $expiresAt = $tokens['expires_at'] ?? null;

        if (! is_int($expiresAt) || $expiresAt <= time() + self::EXPIRY_SKEW) {
            throw new OidcClientException('The OIDC access token is missing or expired.');
        }

        $accessToken = $tokens['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new OidcClientException('The OIDC access token is missing or expired.');
        }

        return $accessToken;
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{access_token: string, expires_at: int}
     */
    private function exchange(string $subjectToken, string $audience, array $parameters): array
    {
        $response = $this->http->asForm()->post($this->discovery->metadata()->tokenEndpoint, [
            ...$parameters,
            'grant_type' => self::EXCHANGE_GRANT,
            'client_id' => (string) config('oidc-client.client_id'),
            'subject_token' => $subjectToken,
            'subject_token_type' => self::ACCESS_TOKEN_TYPE,
            'audience' => $audience,
        ]);

        $accessToken = $response->json('access_token');

        if ($response->failed() || ! is_string($accessToken) || $accessToken === '') {
            throw new OidcClientException('The token endpoint rejected the token exchange.');
        }

        $expiresIn = is_numeric($response->json('expires_in')) ? (int) $response->json('expires_in') : 60;

        return [
            'access_token' => $accessToken,
            'expires_at' => time() + $expiresIn,
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function cacheKey(string $audience, array $parameters): string
    {
        ksort($parameters);

        return hash('sha256', json_encode([$audience, $parameters], JSON_THROW_ON_ERROR));
    }
}
