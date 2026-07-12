<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Token;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use phpseclib3\Crypt\RSA;

class JwksKeyResolver
{
    public function __construct(private readonly OidcDiscovery $discovery) {}

    public function publicKeyPem(string $kid): string
    {
        foreach ($this->discovery->jwks() as $jwk) {
            if (($jwk['kid'] ?? null) !== $kid) {
                continue;
            }

            if (! isset($jwk['n'], $jwk['e'])) {
                throw new OidcClientException("The JWKS key [{$kid}] is missing modulus/exponent.");
            }

            $key = RSA::loadFormat('JWK', (string) json_encode($jwk));

            return (string) $key->toString('PKCS8');
        }

        throw new OidcClientException("No JWKS key matches the token kid [{$kid}].");
    }
}
