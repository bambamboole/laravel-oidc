<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use Laravel\Passport\Bridge\AccessToken;

/**
 * league 9.4 + lcobucci 5.6 make AccessTokenTrait::toString() non-deterministic: each call mints
 * a fresh microsecond-precision `iat`/`nbf`, so the JWT string changes per call. BearerTokenResponse
 * serializes the access token independently of getExtraParams(), which means the at_hash computed
 * inside IdTokenBuilder would hash a different string than the returned access_token. Memoizing the
 * serialization guarantees both read the exact same JWT.
 */
class OidcAccessToken extends AccessToken
{
    private ?string $serialized = null;

    public function toString(): string
    {
        return $this->serialized ??= parent::toString();
    }
}
