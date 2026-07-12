<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Relying party
    |--------------------------------------------------------------------------
    |
    | This package turns a Laravel app into an OpenID Connect relying party: it
    | drives the Authorization-Code + PKCE flow against any OIDC provider,
    | validates the returned id_token against the provider's JWKS, and logs the
    | resolved user into the configured guard. For self-SSO, point `issuer` at
    | your own laravel-oidc provider.
    |
    */

    'enabled' => env('OIDC_RP_ENABLED', false),

    'issuer' => env('OIDC_RP_ISSUER'),

    'client_id' => env('OIDC_RP_CLIENT_ID'),

    'client_secret' => env('OIDC_RP_CLIENT_SECRET'),

    'redirect_uri' => env('OIDC_RP_REDIRECT_URI'),

    'scopes' => ['openid', 'profile', 'email'],

    'login_guard' => env('OIDC_RP_LOGIN_GUARD', 'web'),

    'routes' => [
        'login' => ['path' => 'login', 'name' => 'login'],
        'callback' => ['path' => 'login/callback', 'name' => 'login.callback'],
        'logout' => ['path' => 'logout', 'name' => 'logout'],
    ],

    'redirect_after_login' => env('OIDC_RP_HOME', '/dashboard'),

    'post_logout_redirect_uri' => env('OIDC_RP_POST_LOGOUT_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Discovery cache
    |--------------------------------------------------------------------------
    |
    | The provider's discovery document and JWKS are cached for this many
    | seconds to avoid an HTTP round-trip on every authentication request.
    |
    */

    'discovery_cache_ttl' => (int) env('OIDC_RP_DISCOVERY_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Clock skew
    |--------------------------------------------------------------------------
    |
    | Allowed leeway, in seconds, when validating the id_token `exp`/`nbf`/`iat`
    | claims.
    |
    */

    'leeway' => (int) env('OIDC_RP_LEEWAY', 60),
];
