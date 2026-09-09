---
title: Upgrading from the Passport-backed releases
description: What changed when the package took ownership of the OAuth2 core, and what you have to do about it.
---

Up to 0.22 the package built on **Laravel Passport**. It now owns the OAuth2 core directly on top
of `league/oauth2-server`: its own tables, models, repositories and controllers. Passport is no
longer a dependency.

This is a clean break. **No data migration ships with the package** — the new tables start empty.

## Realms and routes

Two further breaking changes land alongside the Passport removal.

### Every endpoint moved below `/realms/{realm}`

The package serves one OpenID Provider per realm, addressed by path. A single-realm install runs
under the configured `oidc.realm` (`'default'` unless set), so the token endpoint moves from
`/oauth/token` to `/realms/default/oauth/token`, and the issuer from `https://id.example.com` to
`https://id.example.com/realms/default`.

**Every relying party has to be repointed** at the new discovery URL
(`/realms/{realm}/.well-known/openid-configuration`). Clients that pin the issuer will reject
tokens until they are updated. See [Realms](/provider/realms/).

### `oidc.handlers` and `oidc.routes.prefix` are gone

Routes are registered from a plain routes file. Controllers are still replaceable — bind your own
over the package class in the container:

```php
$this->app->bind(UserinfoController::class, MyUserinfoController::class);
```

Changing an endpoint's path and disabling an endpoint are no longer supported.
`oidc.dcr.enabled` still gates the registration endpoint, and `oidc.routes.middleware` still
applies to every route. Route names are unchanged. See [Routes](/introduction/route-handlers/).

## What you have to do

### 1. Run the new migrations

```bash
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate
```

Four tables are added: `oidc_clients`, `oidc_access_tokens`, `oidc_refresh_tokens`,
`oidc_auth_codes`. Passport's `oauth_*` tables are untouched and no longer read — drop them once
you are satisfied with the cutover.

### 2. Re-provision your clients

Client rows are not copied. Recreate them with `oidc:provision-client` (or
[dynamic client registration](/provider/dynamic-client-registration/)) and hand the new
`client_id`/`client_secret` to each relying party.

A client now carries a `client_id` separate from its primary key, so it can be renamed later
without rewriting its tokens. A client created by the package uses its key as the `client_id`
unless you give it a readable one.

### 3. Everyone signs in again

Access tokens, refresh tokens and authorization codes are not migrated. Existing sessions end at
the cutover.

### 4. Swap the user-model trait and contract

```diff
-use Laravel\Passport\Contracts\OAuthenticatable;
-use Laravel\Passport\HasApiTokens;
+use Bambamboole\LaravelOidc\Server\Users\Concerns\HasOidcTokens;
+use Bambamboole\LaravelOidc\Server\Users\OAuthenticatable;

 class User extends Authenticatable implements OAuthenticatable
 {
-    use HasApiTokens;
+    use HasOidcTokens;
 }
```

`$user->createToken()`, `currentAccessToken()`, `withAccessToken()` and `tokenCan()` keep working.
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
| `oidc.passport.scopes` | `oidc.scopes.catalog` |
| `oidc.passport.token_model` | *(removed — the package owns its token model)* |
| `passport.guard` | `oidc.auth.guard` |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | `OIDC_PRIVATE_KEY` / `OIDC_PUBLIC_KEY`, or a keypair in `oidc.keys.path` |

Two keys are new: `oidc.token_lifetimes.refresh_token` (was `Passport::refreshTokensExpireIn()`)
and `oidc.keys.path` (was Passport's key path).

### 7. Replace the runtime and test APIs

| Old | New |
| --- | --- |
| `Passport::tokensCan([...])` | `Oidc::tokensCan([...])` |
| `Passport::actingAs($user, $scopes, $guard)` | `Oidc::actingAs($user, $scopes, $guard)` |
| `Passport::authorizationView(...)` | bind the [`ConsentView` contract](/provider/endpoints/#consent-view-required) |
| `Laravel\Passport\Http\Middleware\CheckToken` | `Bambamboole\LaravelOidc\Server\Tokens\Middleware\CheckScopes` |
| `Laravel\Passport\Client` / `Token` | `Bambamboole\LaravelOidc\Server\Clients\Client` / `Token` |
| `Laravel\Passport\ClientRepository` | `Bambamboole\LaravelOidc\Server\Clients\ClientRepository` |

## What was dropped

- **`POST /realms/{realm}/oauth/token/refresh`** — Passport's cookie-based SPA refresh. The package never wired
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
