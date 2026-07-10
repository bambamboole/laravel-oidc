<?php
declare(strict_types=1);

return [
    'issuer' => env('OIDC_ISSUER'),

    'id_token_ttl' => (int) env('OIDC_ID_TOKEN_TTL', 3600),

    'endpoints' => [
        'userinfo' => true,
        'end_session' => true,
        'introspection' => true,
        'revocation' => true,
    ],

    'claims_supported' => [
        'iss', 'sub', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'at_hash', 'azp',
        'name', 'email', 'email_verified', 'locale', 'zoneinfo', 'updated_at',
    ],

    'additional_public_keys' => [],

    'logout_redirect' => '/',
];
