---
title: Upgrading from the Passport-backed releases
description: What changed when the package took ownership of the OAuth2 core, and what you have to do about it.
---

Up to 0.22 the package built on **Laravel Passport**. It now owns the OAuth2 core outright: its
own tables, models, grants and controllers, with no OAuth2 library underneath. Neither Passport
nor `league/oauth2-server` is a dependency any more.

This is a clean break. **No data migration ships with the package** — the new tables start empty.

## 0.31: the auth screens serve their own component endpoints

A realm that always requires a second factor parks the login on the enrollment
screen before any session exists — the user is deliberately a guest on every
guard, and the subject comes from the pending login instead. The screen rendered,
but the wizard's own Next button drives a Lattice form endpoint, and those run
behind `['web', 'auth']`, so the request that begins the enrollment was rejected
and the user could not get past the screen.

`oidc-ui` now registers a Lattice endpoint area and serves the auth screens'
components from it, so they call back into endpoints that do not demand a
session. This needs `lattice-php/lattice` **0.77 or newer**; the constraint was
raised accordingly.

Two things are worth knowing if you have customised routing:

- `config/oidc.php` gains `routes.screen_middleware`, applied to the interactive
  screens only. `routes.middleware` still covers every package route, protocol
  endpoints included. `oidc-ui` sets `screen_middleware` for you in its
  `register()`; set it yourself only if you are not using that package.
- The area mounts its endpoints below `/oidc-ui` (below `/realms/{realm}/oidc-ui`
  in `path` routing). Nothing serves that prefix today, but a host application
  that does must move out of the way.

## 0.31: a first-party client per realm

`oidc_clients.provisioning_key` carried a unique index across the whole table, so only one realm in
a deployment could hold a first-party client. `oidc:install-self` and `oidc:client` failed in every
other realm with *"Self-SSO is already provisioned for another realm"*. The index is now
`(realm_id, provisioning_key)` and the provisioner looks the client up within the current realm, so
each realm provisions and reconciles its own.

The migration is rewritten rather than stacked. Re-publish it and, on a database that already ran
the old one, drop the single-column index and add the composite one:

```bash
php artisan vendor:publish --tag=oidc-migrations --force
```

```sql
DROP INDEX oidc_clients_provisioning_key_unique ON oidc_clients;
CREATE UNIQUE INDEX oidc_clients_realm_id_provisioning_key_unique
    ON oidc_clients (realm_id, provisioning_key);
```

`FirstPartyClientProvisioner` no longer takes a `RealmResolver`; it resolved one only for the
cross-realm check that is gone. Nothing else about provisioning changed.

## 0.30: flatter config keys

Four keys in `config/oidc.php` moved. `mergeConfigFrom` merges only the first level of a config
array, so a published copy keeps whatever shape it was published with — rename the keys in your
own `config/oidc.php`, or re-publish it with
`php artisan vendor:publish --tag=oidc-config --force` and re-apply your changes.

| Old | New |
| --- | --- |
| `oidc.tokens.lifetimes.*` | `oidc.tokens.*` |
| `oidc.scopes.catalog` | `oidc.scopes` (the catalog is the value itself) |
| `oidc.clients.first_party.provision.*` | `oidc.install_self.*` |
| `oidc.session.token.session_key` | *(removed — the key is a constant on `SessionTokenIssuer`)* |

`oidc.auth` was four concerns in one key, and the only key in the file that nested a settings group
inside a settings group. It is now one flat key per `Realm` settings object, the way every other
key in the file already worked:

| Old | New |
| --- | --- |
| `oidc.auth.{guard,provider,api_guard}` | unchanged — deployment-wide, not per realm |
| `oidc.auth.username` | `oidc.login.username` |
| `oidc.auth.login_route` | `oidc.login.route` |
| `oidc.auth.home` | `oidc.login.home` |
| `oidc.auth.logout_redirect` | `oidc.login.logout_redirect` |
| `oidc.auth.acr_values.single_factor` | `oidc.login.acr_single_factor` |
| `oidc.auth.acr_values.multi_factor` | `oidc.login.acr_multi_factor` |
| `oidc.auth.methods` | `oidc.authentication.methods` |
| `oidc.auth.mfa` | `oidc.authentication.mfa` |
| `oidc.auth.email_verification_required` | `oidc.authentication.email_verification_required` |
| `oidc.auth.factors` | `oidc.credentials.factors` |
| `oidc.auth.two_factor.challenge_providers` | `oidc.credentials.challenge_providers` |
| `oidc.auth.two_factor.secret_length` | `oidc.credentials.totp_secret_length` |
| `oidc.auth.two_factor.window` | `oidc.credentials.totp_window` |
| `oidc.auth.two_factor.recovery_codes` | `oidc.credentials.recovery_codes` |
| `oidc.auth.password.*` | `oidc.password_policy.*` |

Every environment variable keeps its name, so a deployment that configures the package through the
environment has nothing to change.

No behavior changed and nothing new is configurable. `oidc.tokens` and `oidc.scopes` each held a
single child; `install_self` collects the input to the one-shot `oidc:install-self` command, which
is not client configuration and is never read at runtime.

Alongside the renames, `resources`, `social.providers` and `routes.domains` are now merged by name
on top of the first-level merge — see
[How a published config is merged](/introduction/configuration/#how-a-published-config-is-merged).
A published config that defines its own social providers now keeps the shipped ones instead of
replacing them; if you were relying on a published `social.providers` map to *hide* a shipped
provider, note that a provider without a configured `client_id` is inert anyway.

## 0.30: `Realm::identifier()`

The `Realm` contract's `id()` is now `identifier()`. On an Eloquent model `id()` shadowed the `id`
attribute — `$realm->id` resolved as a relation — so rename the method on your implementation;
what it returns is unchanged.

## 0.30: new migrations

Two migrations ship with 0.30: `oidc_password_reset_tokens`, and a `session_id` column on
`oidc_sessions` that records the browser session each login happened in. Publish and run them:

```bash
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate
```

## 0.30: realm-scoped password reset links

Reset links moved out of Laravel's `password_reset_tokens` into the package's own
`oidc_password_reset_tokens` table, keyed by user and bound to the realm that sent them: an email
address is only unique within a realm, so the email-keyed table let one realm's request replace
another's link. Links sent before the upgrade stop working.

The package no longer reads `config('auth.passwords')`. The link lifetime is
`oidc.tokens.password_reset` (seconds), the user provider is `oidc.auth.provider`, and the resend
throttle is fixed at a minute. `SendPasswordResetLink` sends the link of the realm it runs in — wrap
it in `CurrentRealm::runAs()` to reset another realm's user.

## 0.26: a scope belongs to one resource

Scopes are no longer a flat catalog per realm. Every scope belongs to exactly one resource server
and only resolves for requests that name it, so two APIs can carry the same scope value and mean
different things by it — see [Scopes & claims](/provider/scopes-and-claims/). A realm that
registers no resources under `oidc.resources` keeps the behavior it had: the whole catalog belongs
to the realm and is requestable without a `resource`.

What changed regardless:

- **A catalog scope a resource lists in `oidc.resources` is no longer requestable without a
  `resource`.** It now belongs to that resource alone; a request without `resource` gets the realm
  default audience, and a request for another resource gets `invalid_scope`. Narrowing the
  audience with `resource` at the token endpoint drops the scopes the narrowed resource does not
  own. The OIDC standard scopes (`openid`, `profile`, `email`) belong to the realm but keep
  working under every audience.
- **`ScopeCatalog::scopes()` takes the requested resources:**
  `scopes(array $audiences): array<string, string>`. It is asked for the resources of the request
  — never an empty list; the realm's issuer URL stands for the realm's own scopes — and returns
  only what those resources own. An inline `[scope => description]` catalog is split for you by
  the resource lists in `oidc.resources`.
- **`ScopeRepository` takes them too:** `all(array $audiences = [])`,
  `find(string $identifier, array $audiences = [])` and
  `finalize(array $requested, string $grantType, ?Client $client, ?string $userIdentifier = null, array $audiences = [])`.
  An empty list means the realm's default audience, the same convention the token minter follows.
  A custom repository must widen its signatures; a caller that passes nothing keeps today's
  meaning.
- **`Client::allowsScope()` takes them too:** `allowsScope(string $scope, array $audiences = [])`,
  alongside the new `defaultScopes()`, `optionalScopes()` and a widened
  `assignedScopes(array $audiences = [])`. `*` among the optional scopes now stands for every
  scope the *requested* resources own, not for every scope in the realm. An assignment entry may
  name the resource that owns the scope (`'https://api.internal/orders read'`), which limits it to
  requests for that resource; bare entries are unchanged.
- **`scopes_supported` in the discovery document is the union** over the realm and every
  registered resource. Each resource's own RFC 9728 metadata still lists only its scopes.
- **`ScopeGrant::finalize()` gained the same trailing `array $audiences = []`.**

Nothing about how scopes are stored changed: no migration, and no change to the shape of
`default_scopes` / `optional_scopes`.

## 0.26: the realm can hold a login until the user acts

A realm can now refuse to finish a login until the user has confirmed their email address,
replaced an expired password, or enrolled a second factor — see
[Required actions](/auth/required-actions/). Nothing is required by default, so a deployment that
changes no configuration keeps the behavior it had. What changed regardless:

- **`Realm` gained `authentication()`.** A model implementing the contract must add it, or delegate
  it to `ConfiguredRealm` like the other settings.
- **`LoginOutcome` gained `RequiredAction`.** An exhaustive `match` over it needs a fourth arm.
- **`LoginFinalizer` gained `finish()`.** A custom flow that verified its own credential should
  call it rather than `complete()`, which asks the realm nothing and logs the user straight in.
- **The email-verification and factor-enrollment routes no longer run the identity guard.** They
  keep their names and paths, but sit behind `RequireActionSubject`, which accepts a session or a
  pending login. Factor enrollment still confirms a password from a live session; mid-login it
  cannot and does not.
- **`$api->requireMfa()` for a user without a factor no longer denies the login** when a factor
  could be enrolled — it holds the login on enrollment instead, matching `mfa = always`. It still
  denies when nothing could satisfy the demand.
- **`PasswordCredential::isExpired()` is enforced.** With `password_policy.max_age_days` set, an
  expired password now holds the login on the new `identity.password.change` screen instead of
  merely reporting. A post-login hook that used to deny on it can be deleted. The rotation clock
  starts at a user's first password login the package sees, so nobody is expired retroactively.

## 0.24: audiences are requested, not distributed

An access token minted without an RFC 8707 `resource` is now addressed to the realm issuer URL
only, the default resource indicator of RFC 9068 §3. Before, it carried every realm audience,
so any login token passed every `CheckAudience` route. A client that needs a token for a
specific resource server names it with `resource` at the authorization endpoint (or at the
client-credentials grant, or as the `audience` of a token exchange); the value must be on the
client's `allowed_exchange_audiences`.

- `oidc.tokens.audiences` and `oidc.protected_resources` merge into `oidc.resources`: the resource
  servers the realm serves besides itself, keyed by identifier. A path-relative key is published as
  RFC 9728 metadata as before; an absolute URI is a resource the `auth:oidc` guard accepts and a
  client may request.
- `oidc_access_tokens` and `oidc_auth_codes` gain an `audience` column in their create migrations, so
  a refresh keeps the audience of the token it replaces. Re-run them (the 0.23 tables start empty
  anyway) or add the two nullable JSON columns by hand.
- `Realm` implementations add `resources(): ResourceSettings`; `TokenSettings::` is gone.
- `CheckAudience` answers a token for another resource with `401 invalid_token`, as RFC 6750 §3.1
  prescribes, rather than `403`.

### Signing keys live in the database

`EnvSigningKeyStore` and the `OIDC_PRIVATE_KEY` / `OIDC_PUBLIC_KEY` / `OIDC_PREVIOUS_PUBLIC_KEY`
variables are gone, as is the `oauth-*.key` file fallback and `oidc:rotate-keys --print`. The
keypair lives in `oidc_signing_keys` through `DatabaseSigningKeyStore`, the only shipped store; run
`php artisan oidc:rotate-keys --if-missing` once after migrating and drop the variables from your
environment. Tokens signed by the env key stop verifying at the cutover. The `Keys` domain is
now `SigningKeys`: `Server\Keys\*` and `Shared\Keys\*` are `Server\SigningKeys\*` and
`Shared\SigningKeys\*`. Host-app tests get a key from `InteractsWithOidc::installSigningKey()`.

## Realms and routes

Two further breaking changes land alongside the Passport removal.

### Realms

The package now serves realms — isolated sets of clients, tokens, sessions and signing keys. A
single-realm deployment (the default, `oidc.routes.realms` = `single`) keeps every endpoint where
it was: `/oauth/token` stays `/oauth/token` and the issuer stays `https://id.example.com`, so no
relying party has to be repointed. Set `OIDC_REALM` if you want the realm to carry a name other
than `default`.

Deployments that serve more than one realm set `oidc.routes.realms` to `path` or `domain`. With
`path` every endpoint moves below `/realms/{realm}` and the issuer becomes
`https://id.example.com/realms/{realm}`; with `domain` each realm keeps the canonical paths on its
own host and the issuer is that host. Either way **every relying party has to be repointed** at the
new discovery URL. See [Realms](/provider/realms/).

### `oidc.handlers` and `oidc.routes.prefix` are gone

Routes are registered from a plain routes file. Controllers are still replaceable — bind your own
over the package class in the container:

```php
$this->app->bind(UserinfoController::class, MyUserinfoController::class);
```

Changing an endpoint's path and disabling an endpoint are no longer supported.
`oidc.clients.registration.enabled` still gates the registration endpoint, and `oidc.routes.middleware` still
applies to every route. Route names are unchanged. See [Routes](/introduction/route-handlers/).

## What you have to do

### 1. Run the new migrations

```bash
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate
```

Five tables are added: `oidc_clients`, `oidc_access_tokens`, `oidc_refresh_tokens`,
`oidc_auth_codes`, `oidc_consents`. `oidc_access_token_contexts` is gone — the access token
carries its `context_id` itself. Passport's `oauth_*` tables are untouched and no longer read —
drop them once you are satisfied with the cutover.

### 2. Re-provision your clients

Client rows are not copied. Recreate them with `oidc:client --first-party` (or
[dynamic client registration](/provider/dynamic-client-registration/)) and hand the new
`client_id`/`client_secret` to each relying party.

A client now carries a `client_id` separate from its primary key, so it can be renamed later
without rewriting its tokens. A client created by the package uses its key as the `client_id`
unless you give it a readable one.

Every client also carries a [scope assignment](/provider/scopes-and-claims/#client-scope-assignment):
`default_scopes` and `optional_scopes` replace the single allow-list. New clients take the realm's
`clients.default_scopes` / `clients.optional_scopes` (with the defaults, anything may be requested
and nothing is granted unasked); `oidc:client --default-scope` / `--optional-scope` override them.
`oidc.clients.registration.default_scopes` is gone.

### 3. Everyone signs in again

Access tokens, refresh tokens and authorization codes are not migrated. Existing sessions end at
the cutover.

### 4. Swap the user-model trait and contract

```diff
-use Laravel\Passport\Contracts\OAuthenticatable;
-use Laravel\Passport\HasApiTokens;
+use Bambamboole\LaravelOidc\Server\Tokens\Concerns\HasAccessTokens;
+use Bambamboole\LaravelOidc\Server\Tokens\Contracts\OAuthenticatable;

 class User extends Authenticatable implements OAuthenticatable
 {
-    use HasApiTokens;
+    use HasAccessTokens;
 }
```

`$user->createToken()`, `currentAccessToken()`, `withAccessToken()` and `tokenCan()` keep working.
`createToken()` takes an optional third argument, an array stored with the token and served back
by `currentAccessToken()->context()` — the replacement for subclassing Passport's token model to
hang extra columns (a tenant id, say) on it.
`currentAccessToken()` now returns a `CurrentAccessToken`, whose `scopes()`, `clientId()`, `can()`
and `revoke()` replace the old `oauth_*` magic properties.

### 5. Replace the scheduled purge

```diff
-Schedule::command('passport:purge')->daily();
+Schedule::command('oidc:purge')->daily();
```

### 6. Rename the config keys

| Old | New |
| --- | --- |
| `oidc.passport.scopes` | `oidc.scopes` |
| `oidc.passport.token_model` | *(removed — the package owns its token model)* |
| `passport.guard` | `oidc.auth.guard` |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | *(removed — the keypair lives in `oidc_signing_keys`; run `oidc:rotate-keys --if-missing`)* |

One key is new: `oidc.tokens.refresh_token` (was `Passport::refreshTokensExpireIn()`).

### 7. Replace the runtime and test APIs

| Old | New |
| --- | --- |
| `Passport::tokensCan([...])` | `config(['oidc.scopes' => [...]])` |
| `Passport::actingAs($user, $scopes, $guard)` | `$this->actingAsOidcUser($user, $scopes, $guard)` |
| `Passport::authorizationView(...)` | bind the [`ConsentView` contract](/provider/endpoints/#consent-view-required) |
| `Laravel\Passport\Http\Middleware\CheckToken` | `Bambamboole\LaravelOidc\Server\Tokens\Http\Middleware\CheckScopes` |
| `Laravel\Passport\Client` / `Token` | `Bambamboole\LaravelOidc\Server\Clients\Models\Client` / `Bambamboole\LaravelOidc\Server\Tokens\Models\AccessToken` |
| `Laravel\Passport\ClientRepository` | `Bambamboole\LaravelOidc\Server\Clients\ClientRepository` |

## What was dropped

- **`POST /oauth/token/refresh`** — Passport's cookie-based SPA refresh. The package never wired
  the cookie guard that would have validated its output. Use the
  [browser-fetch session token](/advanced/browser-fetch/) instead.
- **Passport's events** (`AccessTokenCreated`, `RefreshTokenCreated`, `AccessTokenRevoked`). Listen
  to the package's [audit events](/provider/audit-logging/) instead.
- **The `passport.*` config fallbacks** for signing keys and the guard.

## What got better

- The authorization server is built per request, so a signing-key rotation takes effect without
  restarting the workers — which is what makes the [database key store](/provider/key-rotation/#the-database-store)
  usable.
- Personal access tokens are minted directly instead of through an internal HTTP request, so the
  `personal_access_token` trigger runs as a normal step rather than hooking token persistence.
- Clients carry per-client token TTLs, `consent_required`, and a `token_endpoint_auth_method`.
- `user_id` columns are uuid-typed, matching the uuid-keyed user models the package already required.
