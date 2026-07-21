<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Token;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use phpseclib3\Crypt\RSA;
use Throwable;

class JwksKeyResolver
{
    /** @var array<string, string> */
    private array $pemByKid = [];

    public function __construct(private readonly OidcDiscovery $discovery) {}

    public function publicKeyPem(string $kid): string
    {
        return $this->pemByKid[$kid] ??= $this->resolve($kid);
    }

    private function resolve(string $kid): string
    {
        return $this->findKeyInJwks($kid, $this->discovery->jwks())
            ?? $this->findKeyInJwks($kid, $this->discovery->jwks(fresh: true))
            ?? throw new OidcClientException("No JWKS key matches the token kid [{$kid}].");
    }

    /**
     * @param  array<int, array<string, mixed>>  $jwks
     */
    private function findKeyInJwks(string $kid, array $jwks): ?string
    {
        foreach ($jwks as $jwk) {
            if (($jwk['kid'] ?? null) !== $kid) {
                continue;
            }

            if (! isset($jwk['n'], $jwk['e'])) {
                throw new OidcClientException("The JWKS key [{$kid}] is missing modulus/exponent.");
            }

            try {
                $key = RSA::loadFormat('JWK', (string) json_encode($jwk));

                return (string) $key->toString('PKCS8');
            } catch (Throwable $e) {
                throw new OidcClientException("The JWKS key [{$kid}] could not be parsed.", 0, $e);
            }
        }

        return null;
    }
}
