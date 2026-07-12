<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Discovery;

use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;

class OidcDiscovery
{
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    public function metadata(): ProviderMetadata
    {
        $issuer = $this->issuer();

        $doc = $this->cache->remember(
            'oidc-client:discovery:'.$issuer,
            $this->ttl(),
            function () use ($issuer): array {
                $doc = $this->http->get($issuer.'/.well-known/openid-configuration')->throw()->json();

                if (! is_array($doc)) {
                    throw new OidcClientException('The OIDC discovery document response was not a JSON object.');
                }

                return $doc;
            },
        );

        $metadata = ProviderMetadata::fromArray($doc);

        if (rtrim($metadata->issuer, '/') !== $issuer) {
            throw new OidcClientException('The discovery document issuer does not match the configured issuer.');
        }

        return $metadata;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function jwks(bool $fresh = false): array
    {
        $metadata = $this->metadata();

        if ($fresh) {
            $this->cache->forget('oidc-client:jwks:'.$metadata->issuer);
        }

        return $this->cache->remember(
            'oidc-client:jwks:'.$metadata->issuer,
            $this->ttl(),
            function () use ($metadata): array {
                $keys = $this->http->get($metadata->jwksUri)->throw()->json('keys', []);

                if (! is_array($keys)) {
                    throw new OidcClientException('The OIDC JWKS response was not a JSON object.');
                }

                return $keys;
            },
        );
    }

    private function issuer(): string
    {
        $issuer = config('oidc-client.issuer');

        if (! is_string($issuer) || $issuer === '') {
            throw new OidcClientException('No OIDC issuer has been configured (oidc-client.issuer).');
        }

        return rtrim($issuer, '/');
    }

    private function ttl(): int
    {
        return (int) config('oidc-client.discovery_cache_ttl', 3600);
    }
}
