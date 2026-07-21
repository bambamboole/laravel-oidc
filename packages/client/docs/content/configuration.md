---
title: Configuration
description: Every key in config/oidc-client.php, including the route handler map.
sidebar:
  order: 3
---

Publish the config with `php artisan vendor:publish --tag=oidc-client-config`. Every key is
listed below with its default and the environment variable that overrides it.

## Relying party

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `false` (`OIDC_RP_ENABLED`) | Master switch. No routes are registered while this is off. |
| `issuer` | `env('OIDC_RP_ISSUER')` | The provider's issuer URL. Discovery, JWKS, and all token validation derive from it. |
| `client_id` | `env('OIDC_RP_CLIENT_ID')` | The client id registered at the provider. |
| `client_secret` | `env('OIDC_RP_CLIENT_SECRET')` | Optional — omit for a public client. Sent to the token endpoint when set. |
| `redirect_uri` | `env('OIDC_RP_REDIRECT_URI')` | The absolute callback URL registered at the provider. |
| `scopes` | `['openid', 'profile', 'email']` | The scopes requested on every authorization request. |
| `login_guard` | `web` (`OIDC_RP_LOGIN_GUARD`) | The guard the resolved user is logged into. |
| `redirect_after_login` | `/dashboard` (`OIDC_RP_HOME`) | Where to send the user after a successful login (via `redirect()->intended(...)`). |
| `post_logout_redirect_uri` | `env('OIDC_RP_POST_LOGOUT_REDIRECT_URI')` | Forwarded to the provider's end-session endpoint on logout. Must be registered on the client at the provider. |

## Discovery & validation

| Key | Default | Description |
| --- | --- | --- |
| `discovery_cache_ttl` | `3600` (`OIDC_RP_DISCOVERY_TTL`) | Seconds the discovery document and JWKS are cached, avoiding an HTTP round-trip per authentication. |
| `leeway` | `60` (`OIDC_RP_LEEWAY`) | Allowed clock skew, in seconds, when validating `exp`/`nbf`/`iat`. |

## Back-channel logout

See [Back-channel logout](/client/backchannel-logout/) for how these fit together.

| Key | Default | Description |
| --- | --- | --- |
| `backchannel_logout.enabled` | `false` (`OIDC_RP_BACKCHANNEL_LOGOUT_ENABLED`) | Registers the logout-token endpoint and the enforcement middleware. |
| `backchannel_logout.auto_middleware` | `true` | Auto-append `EnforceBackchannelLogout` to the middleware group below. Set to `false` to place `oidc-client.enforce-logout` yourself. |
| `backchannel_logout.middleware_group` | `web` | The group the enforcement middleware is appended to. |
| `backchannel_logout.retention_minutes` | `SESSION_LIFETIME`, else `120` (`OIDC_RP_BACKCHANNEL_LOGOUT_RETENTION`) | How long the session pointer and revocation marker live in cache. |

## Route handlers

Every endpoint the package registers lives in `oidc-client.handlers`, keyed by route name —
the same pattern as the provider's [route handlers](/introduction/route-handlers/). Each
entry has a `route` (URI path), a `controller`, and a `middleware` list; set an entry to
`false` to disable that endpoint. The HTTP verb is intrinsic to each endpoint and is not
configurable.

| Route name | Verb | Default path | Purpose |
| --- | --- | --- | --- |
| `login` | `GET` | `login` | Starts the authorization redirect |
| `login.callback` | `GET` | `login/callback` | Handles the provider's redirect back |
| `logout` | `POST` | `logout` | Local + RP-initiated logout |
| `oidc.backchannel-logout` | `POST` | `oidc/backchannel-logout` | Accepts provider-pushed logout tokens (`throttle:60,1`) |

The `login` and `logout` route names are Laravel's conventional ones, so framework
redirects (`route('login')`, auth middleware) resolve to the OIDC flow without extra
configuration. The back-channel route is only registered when
`backchannel_logout.enabled` is true.
