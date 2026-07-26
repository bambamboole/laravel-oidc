<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Token;

use Bambamboole\LaravelOidc\Client\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\RSA;
use Throwable;

class JwksKeyResolver
{
    /** @var array<string, SigningKey> */
    private array $keysByKid = [];

    public function __construct(private readonly OidcDiscovery $discovery) {}

    public function signingKey(string $kid): SigningKey
    {
        return $this->keysByKid[$kid] ??= $this->resolve($kid);
    }

    private function resolve(string $kid): SigningKey
    {
        return $this->findKeyInJwks($kid, $this->discovery->jwks())
            ?? $this->findKeyInJwks($kid, $this->discovery->jwks(fresh: true))
            ?? throw new OidcClientException("No JWKS key matches the token kid [{$kid}].");
    }

    /**
     * @param  array<int, array<string, mixed>>  $jwks
     */
    private function findKeyInJwks(string $kid, array $jwks): ?SigningKey
    {
        foreach ($jwks as $jwk) {
            if (($jwk['kid'] ?? null) !== $kid) {
                continue;
            }

            return new SigningKey($this->publicKeyPem($kid, $jwk), $this->algorithm($kid, $jwk));
        }

        return null;
    }

    /**
     * The JWA algorithm the key signs with: the JWK's own alg when declared,
     * otherwise inferred from the key type. Whether the algorithm is actually
     * acceptable is decided by the validator's allow-list.
     *
     * @param  array<string, mixed>  $jwk
     */
    private function algorithm(string $kid, array $jwk): string
    {
        $alg = $jwk['alg'] ?? null;

        if (is_string($alg) && $alg !== '') {
            return $alg;
        }

        return match (true) {
            ($jwk['kty'] ?? null) === 'RSA' => 'RS256',
            ($jwk['kty'] ?? null) === 'EC' && ($jwk['crv'] ?? null) === 'P-256' => 'ES256',
            default => throw new OidcClientException("The JWKS key [{$kid}] does not declare a supported algorithm."),
        };
    }

    /**
     * @param  array<string, mixed>  $jwk
     */
    private function publicKeyPem(string $kid, array $jwk): string
    {
        $kty = $jwk['kty'] ?? null;

        if ($kty === 'RSA' && ! isset($jwk['n'], $jwk['e'])) {
            throw new OidcClientException("The JWKS key [{$kid}] is missing modulus/exponent.");
        }

        if ($kty === 'EC' && ! isset($jwk['crv'], $jwk['x'], $jwk['y'])) {
            throw new OidcClientException("The JWKS key [{$kid}] is missing curve coordinates.");
        }

        if ($kty !== 'RSA' && $kty !== 'EC') {
            throw new OidcClientException("The JWKS key [{$kid}] has an unsupported key type.");
        }

        try {
            $key = $kty === 'RSA'
                ? RSA::loadFormat('JWK', (string) json_encode($jwk))
                : EC::loadFormat('JWK', (string) json_encode($jwk));

            return (string) $key->toString('PKCS8');
        } catch (Throwable $e) {
            throw new OidcClientException("The JWKS key [{$kid}] could not be parsed.", 0, $e);
        }
    }
}
