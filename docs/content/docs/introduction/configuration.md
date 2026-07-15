---
title: Configuration
description: Every key in config/oidc.php.
---

Publish the config with `php artisan vendor:publish --tag=oidc-config`. Every key is listed
below with its default and the environment variable that overrides it.

## Issuer & tokens

| Key | Default | Description |
| --- | --- | --- |
| `issuer` | `env('OIDC_ISSUER')` | Issuer URL. Falls back to `app.url` when null. All endpoint URLs advertised in discovery are derived from this origin. |
| `token_lifetimes.access_token` | `900` (`OIDC_ACCESS_TOKEN_TTL`) | Interactive (`authorization_code`) and refreshed access-token lifetime in seconds. |
| `token_lifetimes.id_token` | `3600` (`OIDC_ID_TOKEN_TTL`) | `id_token` lifetime in seconds. |
| `token_lifetimes.client_credentials` | `3600` (`OIDC_M2M_ACCESS_TOKEN_TTL`) | Machine-to-machine (`client_credentials`) access-token lifetime. These tokens have no refresh and no session. |
| `session.absolute_lifetime` | `2592000` (`OIDC_SESSION_ABSOLUTE_LIFETIME`) | Absolute cap on an interactive session, from login (30 days). Refresh is denied past this; the user must re-authenticate. Drives `context.expires_at`, the refresh deny-check, and context pruning. |

## Endpoints & discovery

| Key | Default | Description |
| --- | --- | --- |
| `api_guard` | `env('OIDC_API_GUARD', 'api')` | The guard the userinfo endpoint authenticates against. |
| `claims_supported` | standard set | Claims advertised in the discovery document. |
| `logout_redirect` | `/` | Fallback redirect after logout. |
| `handlers` | full map | Every HTTP endpoint the package registers, keyed by route name. See [Route handlers](/introduction/route-handlers/). |

## Token exchange & keys

| Key | Default | Description |
| --- | --- | --- |
| `token_exchange.enabled` | `true` (`OIDC_TOKEN_EXCHANGE_ENABLED`) | Enables the RFC 8693 token-exchange grant. |
| `key_size` | `2048` (`OIDC_KEY_SIZE`) | RSA key size `oidc:rotate-keys` generates. |
| `additional_public_keys` | `[OIDC_PREVIOUS_PUBLIC_KEY]` | Extra PEM public keys published in JWKS; defaults to the previous signing key during rotation. |

## Session token (browser-fetch)

Used by the two-token browser-fetch model — see [Browser-fetch](/advanced/browser-fetch/).

| Key | Default | Description |
| --- | --- | --- |
| `first_party.client_id` | `env('OIDC_FIRST_PARTY_CLIENT')` | The confidential client id used to mint the session root token and perform exchanges on its behalf. |
| `first_party.trusted` | `false` (`OIDC_FIRST_PARTY_TRUSTED`) | Whether the first-party client is auto-consented. |
| `trusted_clients` | `[]` | Additional client ids that skip the consent screen. |
| `login_route` | `login` (`OIDC_LOGIN_ROUTE`) | Route name unauthenticated users are redirected to. |
| `session_token.ttl` | `3600` (`OIDC_SESSION_TOKEN_TTL`) | Root token lifetime in seconds. |
| `session_token.session_key` | `oidc.session_token` | Session key the root token is stored under. |
| `session_token.refresh_skew` | `60` | Seconds before expiry at which the token is re-minted instead of reused. |
| `session_token.scopes` | `null` | Scopes granted to the root token. `null` grants every non-hidden scope. |

## Auth engine

| Key | Default | Description |
| --- | --- | --- |
| `auth.guard` | `identity` (`OIDC_AUTH_GUARD`) | The session guard the auth engine authenticates against. Registered automatically if absent. |
| `auth.provider` | `users` (`OIDC_AUTH_PROVIDER`) | The user provider backing the guard. |
| `auth.home` | `/dashboard` (`OIDC_AUTH_HOME`) | Where to send a user after a successful login/registration. |
| `auth.username` | `email` (`OIDC_AUTH_USERNAME`) | The credential field used to log in. |
| `auth.two_factor.challenge_providers` | `['totp']` | Factor keys offered at the challenge step. |
| `auth.two_factor.secret_length` | `16` | TOTP secret length. |
| `auth.two_factor.window` | `1` | TOTP validation window. |
| `auth.two_factor.recovery_codes` | `8` | Number of recovery codes generated. |
| `auth.factors` | TOTP, recovery, WebAuthn providers | The registered `FactorProvider` classes. |

## Assumptions

- The `api` guard uses Passport's `passport` driver.
- `config('passport.guard')` (set to the `identity` guard by this package) identifies the web
  session used for the interactive authorization and logout flows.
- Signing keys are RS256 (`passport:keys`). Token headers carry a `kid` derived from the RFC
  7638 thumbprint, matched by the JWKS endpoint.
