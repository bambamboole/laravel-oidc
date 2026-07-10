<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use RuntimeException;

final class Jwk
{
    /** @return array{kty: string, use: string, alg: string, kid: string, n: string, e: string} */
    public static function fromPem(string $pem): array
    {
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw new RuntimeException('The given PEM is not a readable public key.');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('Only RSA public keys can be converted to a JWK.');
        }

        $n = self::base64UrlEncode($details['rsa']['n']);
        $e = self::base64UrlEncode($details['rsa']['e']);

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::thumbprint($n, $e),
            'n' => $n,
            'e' => $e,
        ];
    }

    public static function thumbprint(string $n, string $e): string
    {
        // RFC 7638: members in lexicographic order (e, kty, n), no whitespace.
        $json = json_encode(['e' => $e, 'kty' => 'RSA', 'n' => $n]);

        return self::base64UrlEncode(hash('sha256', $json, true));
    }

    private static function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
