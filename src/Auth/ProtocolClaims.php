<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

final class ProtocolClaims
{
    /** @var list<string> */
    public const array RESERVED = [
        'iss', 'sub', 'aud', 'exp', 'iat', 'nbf', 'jti',
        'nonce', 'at_hash', 'c_hash', 'auth_time', 'azp', 'acr', 'amr',
    ];

    public static function isReserved(string $name): bool
    {
        return in_array($name, self::RESERVED, true);
    }
}
