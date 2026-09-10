---
title: Configuration
description: Every key in config/oidc.php.
---

Publish the config with `php artisan vendor:publish --tag=oidc-config`. Every key is listed
below with its default and the environment variable that overrides it.

## Issuer & realm

| Key | Default | Description |
| --- | --- | --- |
| `issuer` | `env('OIDC_ISSUER')` | Issuer URL. Falls back to `app.url` when null. All endpoint URLs advertised in discovery are derived from this origin. |
| `realm` | `'default'` (`OIDC_REALM`) | Identifier of the realm outside a matched route and of the only realm in a single-realm deployment; per-realm settings come from the `Realm` contract. See [Realms](/provider/realms/). |
| `claims_supported` | standard set | Claims advertised in the discovery document. |
| `routes.middleware` | `[]` | Middleware prepended to every registered package route. |
| `routes.realms` | `'single'` (`OIDC_ROUTE_REALMS`) | `single` serves the configured realm from the application root; `path` serves every realm below `/realms/{realm}` with its own issuer. See [Realms](/provider/realms/). |

## Signing keys

| Key | Default | Description |
| --- | --- | --- |
| `keys.store` | `EnvSigningKeyStore::class` | Class-string of the `SigningKeyStore` that holds the signing keypair; `DatabaseSigningKeyStore::class` keeps it in `oidc_signing_keys` — see [Key rotation](/provider/key-rotation/). |
| `keys.private_key` | `env('OIDC_PRIVATE_KEY')` | RS256 private signing key as a PEM string (`\n`-escaped single lines are fine). See [Key rotation](/provider/key-rotation/). |
| `keys.public_key` | `env('OIDC_PUBLIC_KEY')` | The matching public key, published in JWKS. |
| `keys.path` | `env('OIDC_KEY_PATH')` | Directory the env store falls back to for `oauth-private.key`/`oauth-public.key` when no PEM is configured; `null` means `storage_path()`. |
| `keys.size` | `2048` (`OIDC_KEY_SIZE`) | RSA key size `oidc:rotate-keys` generates. |
| `keys.additional_public_keys` | `[OIDC_PREVIOUS_PUBLIC_KEY]` | Extra PEM public keys the env store retains for verification and JWKS; defaults to the previous signing key during rotation. Ignored by `DatabaseSigningKeyStore`, which retains keys as rows — see [Key rotation](/provider/key-rotation/). |

## Tokens & sessions

| Key | Default | Description |
| --- | --- | --- |
| `tokens.audiences` | `[]` | Resource identifiers the realm serves. An access token minted without an explicit audience carries them as `aud`, and the `auth:oidc` guard accepts a token only when its `aud` names one of them or an advertised protected resource. Empty means the realm issuer URL — see [Access tokens](/provider/access-tokens/). |
| `tokens.lifetimes.access_token` | `900` (`OIDC_ACCESS_TOKEN_TTL`) | Interactive (`authorization_code`) and refreshed access-token lifetime in seconds. |
| `tokens.lifetimes.id_token` | `3600` (`OIDC_ID_TOKEN_TTL`) | `id_token` lifetime in seconds. |
| `tokens.lifetimes.client_credentials` | `3600` (`OIDC_M2M_ACCESS_TOKEN_TTL`) | Machine-to-machine (`client_credentials`) access-token lifetime. These tokens have no refresh and no session. |
| `tokens.lifetimes.refresh_token` | `1209600` (`OIDC_REFRESH_TOKEN_TTL`) | Idle cap on an interactive session: a refresh token unused for this long is dead. |
| `session.cookie_name` | `null` (`OIDC_SESSION_COOKIE`) | Name of the provider's session cookie under `path` realm routing; `null` derives `{session.cookie}-oidc-{realm}`. Unused under `single` routing, where provider and application share one session. |
| `session.absolute_lifetime` | `2592000` (`OIDC_SESSION_ABSOLUTE_LIFETIME`) | Absolute cap on an interactive session, from login (30 days). Refresh is denied past this; the user must re-authenticate. Drives `context.expires_at`, the refresh deny-check, and context pruning. |
| `session.token.ttl` | `3600` (`OIDC_SESSION_TOKEN_TTL`) | Root token lifetime in seconds — see [Browser-fetch](/advanced/browser-fetch/). |
| `session.token.session_key` | `oidc.session_token` | Session key the root token is stored under. |
| `session.token.refresh_skew` | `60` | Seconds before expiry at which the root token is re-minted instead of reused. |
| `session.token.scopes` | `null` | Scopes granted to the root token. `null` grants every non-hidden scope. |
| `session.token.guard` | `null` (`OIDC_SESSION_TOKEN_GUARD`) | Guard whose login/logout owns the session token. `null` falls back to `auth.guard`, then the application default guard. Other guards never mint or revoke. |

## Scopes

| Key | Default | Description |
| --- | --- | --- |
| `scopes.catalog` | `[]` | API scope catalog the scope repository consults at enumeration time — an inline `[scope => description]` map or a `ScopeCatalog` class-string. See [Scopes & claims](/provider/scopes-and-claims/). |

## Clients

| Key | Default | Description |
| --- | --- | --- |
| `clients.first_party.client_id` | `env('OIDC_FIRST_PARTY_CLIENT')` | The confidential client id used to mint the session root token and perform exchanges on its behalf. |
| `clients.first_party.trusted` | `false` (`OIDC_FIRST_PARTY_TRUSTED`) | Whether the first-party client is auto-consented. |
| `clients.first_party.provision` | empty lists | Extra provisioning metadata (`redirect_uris`, `post_logout_redirect_uris`, `allowed_exchange_audiences`) applied on top of the `APP_URL`-derived defaults — see [First-party client provisioning](/advanced/first-party-client/). |
| `clients.trusted` | `[]` | Additional client ids that skip the consent screen. |
| `clients.registration.enabled` | `false` (`OIDC_DCR_ENABLED`) | Answers RFC 7591 dynamic client registration on `POST /oauth/register` — see [Dynamic client registration](/provider/dynamic-client-registration/). |
| `clients.registration.allowed_redirect_schemes` | `[]` | Custom URI schemes accepted for registered redirect URIs. |
| `clients.registration.allowed_redirect_domains` | `['*']` | Hosts accepted for http(s) redirect URIs; `*` allows any. |
| `clients.default_scopes` | `[]` | Scopes every new client is assigned and granted without requesting them — see [Client scope assignment](/provider/scopes-and-claims/#client-scope-assignment). |
| `clients.optional_scopes` | `['*']` | Scopes every new client is assigned and granted on request; `*` stands for every catalog scope. |
| `clients.token_exchange` | `true` (`OIDC_TOKEN_EXCHANGE_ENABLED`) | Enables the RFC 8693 token-exchange grant. |

## Auth engine

| Key | Default | Description |
| --- | --- | --- |
| `auth.guard` | `identity` (`OIDC_AUTH_GUARD`) | The session guard the auth engine authenticates against. Registered automatically if absent. |
| `auth.provider` | `users` (`OIDC_AUTH_PROVIDER`) | The user provider backing the guard. |
| `auth.api_guard` | `oidc` (`OIDC_API_GUARD`) | The guard the userinfo endpoint (and resource-server routes using `auth:oidc`) authenticates against. Registered automatically if absent, the same way `auth.guard` is. |
| `auth.home` | `/dashboard` (`OIDC_AUTH_HOME`) | Where to send a user after a successful login/registration. |
| `auth.username` | `email` (`OIDC_AUTH_USERNAME`) | The credential field used to log in. |
| `auth.login_route` | `login` (`OIDC_LOGIN_ROUTE`) | Route name or path unauthenticated users are redirected to. |
| `auth.logout_redirect` | `/` | Fallback redirect after logout. |
| `auth.acr_values` | `['single_factor' => '1', 'multi_factor' => '2']` | The `acr` value a login earns with one method in `amr` and with several; both are advertised as `acr_values_supported`. Substitute URIs or RFC 6711 names your relying parties expect. |
| `auth.password.min_length` | `8` | Minimum length of a new password — see [Password policy](/auth/passwords/#password-policy). |
| `auth.password.mixed_case`, `numbers`, `symbols`, `uncompromised` | `false` | Composition rules of the password policy. |
| `auth.password.history` | `0` | Previous passwords a new one may not repeat; `0` disables the check. |
| `auth.password.max_age_days` | `null` | Days after which `PasswordCredential::isExpired()` reports the password as expired. |
| `auth.two_factor.challenge_providers` | `['totp', 'webauthn']` | Factor keys offered at the challenge step. |
| `auth.two_factor.secret_length` | `16` | TOTP secret length. |
| `auth.two_factor.window` | `1` | TOTP validation window. |
| `auth.two_factor.recovery_codes` | `8` | Number of recovery codes generated. |
| `auth.factors` | TOTP, recovery, WebAuthn providers | The registered `FactorProvider` classes. |

## Audit, resources

| Key | Default | Description |
| --- | --- | --- |
| `audit.enabled` | `true` (`OIDC_AUDIT_ENABLED`) | Records audit events through the sink — see [Audit logging](/provider/audit-logging/). |
| `audit.sink` | `LogAuditSink::class` | Class-string of the `AuditSink` audit records are written to. |
| `audit.log_channel` | `env('OIDC_AUDIT_LOG_CHANNEL')` | Log channel `LogAuditSink` writes to; `null` uses the default channel. |
| `protected_resources` | `[]` | RFC 9728 protected-resource metadata keyed by path — see [Resource servers](/advanced/resource-servers/). |

## Social login

| Key | Default | Description |
| --- | --- | --- |
| `social.link_by_verified_email` | `true` | Attach an upstream identity to an existing local user when the provider reports a matching verified email. |
| `social.auto_provision` | `true` | Create a local user on first social login via the bound `CreateUserFromSocialAccount` action. |
| `social.providers` | `google`, `apple`, `github` entries | The upstream identity providers; each is active only once its `client_id` is set. See [Social login](/auth/social-login/). |

## Assumptions

- The `oidc.auth.api_guard` guard uses the package's `oidc` driver (the RFC 9068 resource-server guard
  — see [Resource servers (CheckAudience)](/advanced/resource-servers/)).
- The interactive authorization and logout flows run through the `identity` guard the package
  registers (`oidc.auth.guard`).
- Signing keys are RS256 — see [Key rotation](/provider/key-rotation/). Token headers carry a
  `kid` derived from the RFC 7638 thumbprint, matched by the JWKS endpoint.
