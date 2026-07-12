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

    'api_guard' => env('OIDC_API_GUARD', 'api'),

    'claims_supported' => [
        'iss', 'sub', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'at_hash', 'azp',
        'name', 'email', 'email_verified', 'locale', 'zoneinfo', 'updated_at',
    ],

    'token_exchange' => [
        'enabled' => env('OIDC_TOKEN_EXCHANGE_ENABLED', true),
    ],

    'key_size' => (int) env('OIDC_KEY_SIZE', 2048),

    'additional_public_keys' => array_values(array_filter([
        str_replace('\n', "\n", (string) env('OIDC_PREVIOUS_PUBLIC_KEY', '')),
    ], static fn (string $pem): bool => trim($pem) !== '')),

    'logout_redirect' => '/',

    'first_party_client' => env('OIDC_FIRST_PARTY_CLIENT') ?: null,

    'session_token' => [
        'ttl' => (int) env('OIDC_SESSION_TOKEN_TTL', 3600),
        'session_key' => 'oidc.session_token',
        'refresh_skew' => 60,
        'scopes' => null,
    ],

    'auth' => [
        'enabled' => env('OIDC_AUTH_ENABLED', true),
        'guard' => env('OIDC_AUTH_GUARD', 'web'),
        'home' => env('OIDC_AUTH_HOME', '/dashboard'),
    ],
];
