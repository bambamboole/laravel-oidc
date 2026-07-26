<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Token;

/**
 * A JWKS entry resolved to verification material: the public key PEM and the
 * JWA algorithm it signs with.
 */
final readonly class SigningKey
{
    public function __construct(
        public string $pem,
        public string $algorithm,
    ) {}
}
