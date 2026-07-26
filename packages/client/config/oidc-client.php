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

    /*
    |--------------------------------------------------------------------------
    | Route handlers
    |--------------------------------------------------------------------------
    |
    | Sparse overrides for the endpoints the package registers, keyed by route
    | name (see the Handler enum). An absent entry registers the endpoint with
    | its package defaults, `false` disables it, and a partial entry overrides
    | only the given keys (`route`, `controller`, `middleware`). The HTTP verb
    | is intrinsic to the endpoint and lives in code, not here. Routes are
    | only registered when `enabled` is true.
    |
    | 'handlers' => [
    |     Handler::Login->value => ['route' => 'sign-in'],
    |     Handler::Logout->value => false,
    | ],
    |
    */

    'handlers' => [],

    /*
    |--------------------------------------------------------------------------
    | Route defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every registered endpoint: `prefix` is prepended to each
    | route path, `middleware` is appended after each endpoint's own stack.
    |
    */

    'routes' => [
        'prefix' => '',
        'middleware' => [],
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

    /*
    |--------------------------------------------------------------------------
    | Back-channel logout
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers an endpoint that accepts OIDC
    | back-channel logout tokens from the provider and terminates the
    | matching local session(s).
    |
    */

    'backchannel_logout' => [
        'enabled' => env('OIDC_RP_BACKCHANNEL_LOGOUT_ENABLED', false),
        'auto_middleware' => true,
        'middleware_group' => 'web',
        'retention_minutes' => (int) env('OIDC_RP_BACKCHANNEL_LOGOUT_RETENTION', (int) env('SESSION_LIFETIME', 120)),
    ],
];
