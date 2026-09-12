---
title: Configuration
description: Every key in config/oidc.php.
---

Publish the config with `php artisan vendor:publish --tag=oidc-config`. Every key is listed
below with its default and the environment variable that overrides it.

## How a published config is merged

Laravel's `mergeConfigFrom` merges only the first level of a config array. A published
`config/oidc.php` therefore owns each of its top-level keys whole: if a later release adds a key
inside one you already define, you will not see it until you add it yourself.

Three keys are exceptions, merged by name the way Laravel merges `database.connections`: `resources`,
`social.providers` and `routes.domains`. Each is a registry — a map of named entries — so a provider
or resource a later release ships appears alongside yours. Your own entry always wins whole and is
never patched into, and the group around a nested registry (`social`, `routes`) keeps its other keys.

Every other key follows the first-level rule, so re-read this page after an upgrade — the
[upgrade guide](/introduction/upgrading/) lists what moved.

## Issuer & realm

| Key | Default | Description |
| --- | --- | --- |
| `issuer` | `env('OIDC_ISSUER')` | Issuer URL. Falls back to `app.url` when null. All endpoint URLs advertised in discovery are derived from this origin. |
| `realm` | `'default'` (`OIDC_REALM`) | Identifier of the realm outside a matched route and of the only realm in a single-realm deployment; per-realm settings come from the `Realm` contract. See [Realms](/provider/realms/). |
| `claims_supported` | standard set | Claims advertised in the discovery document. |
| `routes.middleware` | `[]` | Middleware prepended to every registered package route. |
| `routes.realms` | `'single'` (`OIDC_ROUTE_REALMS`) | Where realms appear in URLs: `single` at the application root, `path` below `/realms/{realm}`, `domain` on a host per realm. See [Realms](/provider/realms/). |
| `routes.domains` | `[]` | `domain` routing only: host => realm identifier. An application with a realm model resolves the host in its own `RealmRepository` instead. |

## Schema

These are read only while migrating, and only by the shipped migrations. They
say where the package's `user_id` and `realm_id` columns point, because both
name a table the application owns rather than one the package ships. A `null`
table writes no foreign key for that column at all.

| Key | Default | Description |
| --- | --- | --- |
| `migrations.users.table` | `'users'` (`OIDC_MIGRATIONS_USERS_TABLE`) | Table every `user_id` column references, with `on delete cascade`. Set it to `null` to leave `user_id` unconstrained. |
| `migrations.users.column` | `'id'` (`OIDC_MIGRATIONS_USERS_COLUMN`) | The column referenced there. It must be unique and, because every `user_id` is a native `uuid`, uuid-typed. |
| `migrations.realms.table` | `null` (`OIDC_MIGRATIONS_REALMS_TABLE`) | Table every `realm_id` column references. Off by default: a realm is an opaque identifier the application owns, and there need not be a table behind it. |
| `migrations.realms.column` | `'slug'` (`OIDC_MIGRATIONS_REALMS_COLUMN`) | The column referenced there. `realm_id` holds the realm's identifier, not its primary key, so this usually names a unique slug column rather than `id`. |

Changing these after the tables exist has no effect — the constraints are
written once, when the migration runs.

## Signing keys

| Key | Default | Description |
| --- | --- | --- |
| `keys.store` | `DatabaseSigningKeyStore::class` | Class-string of the `SigningKeyStore` that holds the signing keypair; the shipped store keeps it in `oidc_signing_keys` — see [Key rotation](/provider/key-rotation/). |
| `keys.size` | `2048` (`OIDC_KEY_SIZE`) | RSA key size `oidc:rotate-keys` generates. |

## Tokens & sessions

| Key | Default | Description |
| --- | --- | --- |
| `tokens.access_token` | `900` (`OIDC_ACCESS_TOKEN_TTL`) | Interactive (`authorization_code`) and refreshed access-token lifetime in seconds. |
| `tokens.id_token` | `3600` (`OIDC_ID_TOKEN_TTL`) | `id_token` lifetime in seconds. |
| `tokens.client_credentials` | `3600` (`OIDC_M2M_ACCESS_TOKEN_TTL`) | Machine-to-machine (`client_credentials`) access-token lifetime. These tokens have no refresh and no session. |
| `tokens.refresh_token` | `1209600` (`OIDC_REFRESH_TOKEN_TTL`) | Idle cap on an interactive session: a refresh token unused for this long is dead. |
| `tokens.password_reset` | `3600` (`OIDC_PASSWORD_RESET_TTL`) | How long a password reset link stays valid, in seconds. See [Password reset](/auth/passwords/#reset-links). |
| `session.cookie_name` | `null` (`OIDC_SESSION_COOKIE`) | Name of the provider's session cookie under `path` realm routing; `null` derives `{session.cookie}-oidc-{realm}`. Unused under `single` routing, where provider and application share one session. |
| `session.absolute_lifetime` | `2592000` (`OIDC_SESSION_ABSOLUTE_LIFETIME`) | Absolute cap on an interactive session, from login (30 days), never extended. Refresh is denied past it and the session becomes eligible for back-channel logout; the authorization endpoint does not re-check it. Drives `context.expires_at`, the refresh deny-check, and context pruning — see [Sessions](/provider/sessions/#lifetimes). |
| `session.token.ttl` | `3600` (`OIDC_SESSION_TOKEN_TTL`) | Root token lifetime in seconds — see [Browser-fetch](/advanced/browser-fetch/). |
| `session.token.refresh_skew` | `60` | Seconds before expiry at which the root token is re-minted instead of reused. |
| `session.token.scopes` | `null` | Scopes granted to the root token. `null` grants every non-hidden scope. |
| `session.token.guard` | `null` (`OIDC_SESSION_TOKEN_GUARD`) | Guard whose login/logout owns the session token. `null` falls back to `auth.guard`, then the application default guard. Other guards never mint or revoke. |

## Scopes

| Key | Default | Description |
| --- | --- | --- |
| `scopes` | `[]` | API scope catalog the scope repository consults at enumeration time — an inline `[scope => description]` map or a `ScopeCatalog` class-string. A scope a resource lists in `resources` belongs to that resource; the rest belong to the realm. See [Scopes & claims](/provider/scopes-and-claims/). |

## Clients

| Key | Default | Description |
| --- | --- | --- |
| `clients.first_party.client_id` | `env('OIDC_FIRST_PARTY_CLIENT')` | The confidential client id used to mint the session root token and perform exchanges on its behalf. |
| `clients.first_party.trusted` | `false` (`OIDC_FIRST_PARTY_TRUSTED`) | Whether the first-party client is auto-consented. |
| `clients.trusted` | `[]` | Additional client ids that skip the consent screen. |
| `clients.registration.enabled` | `false` (`OIDC_DCR_ENABLED`) | Answers RFC 7591 dynamic client registration on `POST /oauth/register` — see [Dynamic client registration](/provider/dynamic-client-registration/). |
| `clients.registration.allowed_redirect_schemes` | `[]` | Custom URI schemes accepted for registered redirect URIs. |
| `clients.registration.allowed_redirect_domains` | `['*']` | Hosts accepted for http(s) redirect URIs; `*` allows any. |
| `clients.default_scopes` | `[]` | Scopes every new client is assigned and granted without requesting them — see [Client scope assignment](/provider/scopes-and-claims/#client-scope-assignment). |
| `clients.optional_scopes` | `['*']` | Scopes every new client is assigned and granted on request; `*` stands for every catalog scope. |
| `clients.token_exchange` | `true` (`OIDC_TOKEN_EXCHANGE_ENABLED`) | Enables the RFC 8693 token-exchange grant. |

## Self-SSO installation

Input to the one-shot `oidc:install-self` command, added on top of the `APP_URL`-derived defaults —
see [First-party client provisioning](/advanced/first-party-client/). Nothing here is read at
runtime; the provisioned client carries the values from then on.

| Key | Default | Description |
| --- | --- | --- |
| `install_self.redirect_uris` | `[]` | Extra redirect URIs for the first-party client. |
| `install_self.post_logout_redirect_uris` | `[]` | Extra post-logout redirect URIs for the first-party client. |
| `install_self.allowed_exchange_audiences` | `[]` | Audiences the first-party client may exchange tokens for. Token exchange is enabled on the client only when at least one is listed. |

## Guards

Deployment-wide: a realm cannot override these.

| Key | Default | Description |
| --- | --- | --- |
| `auth.guard` | `identity` (`OIDC_AUTH_GUARD`) | The session guard the auth engine authenticates against. Registered automatically if absent. |
| `auth.provider` | `users` (`OIDC_AUTH_PROVIDER`) | The user provider backing the guard. |
| `auth.api_guard` | `oidc` (`OIDC_API_GUARD`) | The guard the userinfo endpoint (and resource-server routes using `auth:oidc`) authenticates against. Registered automatically if absent, the same way `auth.guard` is. |

## Login

Where the interactive login lives and what an authentication reports — `LoginSettings`.

| Key | Default | Description |
| --- | --- | --- |
| `login.username` | `email` (`OIDC_AUTH_USERNAME`) | The credential field used to log in. |
| `login.route` | `login` (`OIDC_LOGIN_ROUTE`) | Route name or path unauthenticated users are redirected to. |
| `login.home` | `/dashboard` (`OIDC_AUTH_HOME`) | Where to send a user after a successful login/registration. |
| `login.logout_redirect` | `/` | Fallback redirect after logout. |
| `login.acr_single_factor` | `'1'` | The `acr` value a login earns with one method in `amr`. |
| `login.acr_multi_factor` | `'2'` | The `acr` value a login earns with several. Both are advertised as `acr_values_supported`; substitute URIs or RFC 6711 names your relying parties expect. |

## Authentication

What the realm demands before it hands out a session — `AuthenticationSettings`.

| Key | Default | Description |
| --- | --- | --- |
| `authentication.methods` | `['password', 'passkey', 'social']` | The login methods this realm accepts. A method left out has its routes closed, not merely hidden — see [Login](/auth/login/). |
| `authentication.mfa` | `if_enrolled` (`OIDC_AUTH_MFA`) | How hard the realm insists on a second factor: `never`, `if_enrolled` or `always` — see [Multi-factor](/auth/multi-factor/#requiring-a-factor). |
| `authentication.email_verification_required` | `false` (`OIDC_AUTH_EMAIL_VERIFICATION_REQUIRED`) | Whether an unconfirmed address blocks the login — see [Required actions](/auth/required-actions/). |

## Credentials

The second factors a user can enroll and be challenged with — `CredentialSettings`.

| Key | Default | Description |
| --- | --- | --- |
| `credentials.factors` | TOTP, recovery, WebAuthn providers | The registered `FactorProvider` classes. |
| `credentials.challenge_providers` | `['totp', 'webauthn']` | Factor keys offered at the challenge step. |
| `credentials.totp_secret_length` | `16` | TOTP secret length. |
| `credentials.totp_window` | `1` | TOTP validation window. |
| `credentials.recovery_codes` | `8` | Number of recovery codes generated. |

## Password policy

What a new password must satisfy — `PasswordPolicy`. See [Password policy](/auth/passwords/#password-policy).

| Key | Default | Description |
| --- | --- | --- |
| `password_policy.min_length` | `8` | Minimum length of a new password. |
| `password_policy.mixed_case`, `numbers`, `symbols`, `uncompromised` | `false` | Composition rules of the password policy. |
| `password_policy.history` | `0` | Previous passwords a new one may not repeat; `0` disables the check. |
| `password_policy.max_age_days` | `null` | Days after which the password counts as expired, raising the `update_password` [required action](/auth/required-actions/). |

## Audit, resources

| Key | Default | Description |
| --- | --- | --- |
| `audit.enabled` | `true` (`OIDC_AUDIT_ENABLED`) | Records audit events through the sink — see [Audit logging](/provider/audit-logging/). |
| `audit.sink` | `LogAuditSink::class` | Class-string of the `AuditSink` audit records are written to. |
| `audit.log_channel` | `env('OIDC_AUDIT_LOG_CHANNEL')` | Log channel `LogAuditSink` writes to; `null` uses the default channel. |
| `resources` | `[]` | Resource servers the realm serves besides itself, keyed by identifier with the scopes they advertise. A path-relative key is published as RFC 9728 metadata; every entry is an audience a client may request with `resource` and the `auth:oidc` guard accepts — see [Resource servers](/advanced/resource-servers/). |

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
