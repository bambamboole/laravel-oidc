# laravel-oidc

An OpenID Connect (OIDC) provider layer built on top of **Laravel Passport 13**.

Passport gives you OAuth2. This package adds the OIDC identity layer on top of it:
signed `id_token`s, a discovery document, a JWKS endpoint, `userinfo`, RP-initiated
logout, token introspection and revocation, plus the standard OIDC scopes and claims.
It does not replace Passport — it extends and reconfigures it.

## Requirements

- PHP `^8.4`
- `laravel/passport` `^13.0`
- RS256 signing keys (Passport's `passport:keys`)

## Installation

```bash
composer require bambamboole/laravel-oidc

# Publish and run the migration that adds post_logout_redirect_uris to oauth_clients
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate

# Generate the RSA signing keys Passport (and this package) sign tokens with
php artisan passport:keys

# Optional: publish the config
php artisan vendor:publish --tag=oidc-config
```

The service provider is auto-discovered.

## What it takes over

On registration the package calls `Passport::ignoreRoutes()` and registers the **full
`/oauth/*` route surface itself** (`routes/passport.php` + `routes/oidc.php`). This means:

- The authorization, token, approve/deny, and token-refresh routes are registered by
  this package using its own controllers (so `max_age`, OIDC scopes, and the `id_token`
  response type are wired in).
- **Passport's optional JSON API management routes are *not* registered** (client CRUD,
  personal-access-token management, scope listing, etc.). If your app relies on those,
  register them yourself.
- The access-token entity is swapped to `OidcAccessToken` and the authorization-server
  response type to `IdTokenResponse`.

## Endpoints

| Endpoint | Route | Purpose |
| --- | --- | --- |
| Discovery | `GET /.well-known/openid-configuration` | OIDC provider metadata |
| JWKS | `GET /.well-known/jwks.json` | Public signing keys (RS256) |
| UserInfo | `GET\|POST /oauth/userinfo` | Claims for the bearer token (`auth:api`, requires `openid`) |
| End session | `GET\|POST /oauth/logout` | RP-initiated logout (see threat model) |
| Introspection | `POST /oauth/introspect` | RFC 7662 token introspection (client-authenticated) |
| Revocation | `POST /oauth/revoke` | RFC 7009 token revocation (client-authenticated) |

Each of the last four can be toggled off via config.

## Configuration (`config/oidc.php`)

| Key | Default | Description |
| --- | --- | --- |
| `issuer` | `env('OIDC_ISSUER')` | Issuer URL. Falls back to `app.url` when null. |
| `id_token_ttl` | `3600` | `id_token` lifetime in seconds. |
| `endpoints.userinfo` | `true` | Register the userinfo endpoint. |
| `endpoints.end_session` | `true` | Register the logout endpoint. |
| `endpoints.introspection` | `true` | Register the introspection endpoint. |
| `endpoints.revocation` | `true` | Register the revocation endpoint. |
| `claims_supported` | standard set | Advertised in discovery. |
| `additional_public_keys` | `[]` | Extra PEM public keys to publish in JWKS (key rotation). |
| `logout_redirect` | `/` | Fallback redirect after logout. |

## Extension contracts

### `ScopeRepository`

`Bambamboole\LaravelOidc\Contracts\ScopeRepository` is the catalogue of scopes the
provider understands. The default `PassportScopeRepository` merges the OIDC scopes
(`openid`, `profile`, `email`, `address`, `phone`) over `Passport::$scopes`. Bind your
own to change the catalogue:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\ScopeRepository::class,
    MyScopeRepository::class,
);
```

### `ClaimsResolver`

`Bambamboole\LaravelOidc\Contracts\ClaimsResolver` maps an authenticated user to a
`ClaimSet`. A `ClaimSet` is constructed from a `scope => [claim => value]` map; both the
`id_token` builder and the userinfo endpoint call `forScopes()` on it with the token's
granted scopes, so a claim is only emitted when its scope was granted (null values are
dropped).

```php
use Bambamboole\LaravelOidc\Claims\ClaimSet;
use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class AppClaimsResolver implements ClaimsResolver
{
    public function resolve(Authenticatable $user): ClaimSet
    {
        return new ClaimSet([
            'profile' => ['name' => $user->name],
            'email' => [
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }
}
```

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\ClaimsResolver::class,
    AppClaimsResolver::class,
);
```

## Consent view (required)

The authorization endpoint renders Passport's consent view. You must register one via
`Passport::authorizationView()`, typically in a service provider `boot()`:

```php
use Laravel\Passport\Passport;

Passport::authorizationView(function (array $parameters) {
    return view('oauth.authorize', [
        'client'  => $parameters['client'],
        'user'    => $parameters['user'],
        'scopes'  => $parameters['scopes'],
        'request' => $parameters['request'],
        'authToken' => $parameters['authToken'],
    ]);
});
```

The view posts `auth_token` back to `POST /oauth/authorize` to approve, or sends
`DELETE /oauth/authorize` to deny.

## Scopes behaviour

- The OIDC standard scopes are merged **over** `Passport::$scopes`; scopes your app
  already defines win, so you can override their descriptions.
- **Wildcard (`*`) parity with Passport.** Passport treats `*` as always valid and
  grants it for the `password`, `personal_access`, and `client_credentials` grants
  (e.g. `$user->createToken('cli', ['*'])`). This package mirrors that exactly: `*`
  resolves as a scope and survives finalization for those grant types, and is stripped
  for `authorization_code` (interactive) flows.

## Logout threat model (`/oauth/logout`)

RP-initiated logout is a known CSRF surface (a forged `GET` can log a victim out). The
end-session endpoint therefore only destroys the session when the request proves intent:

- **Valid `id_token_hint`** (signature + issuer verified) → log out and redirect to a
  registered `post_logout_redirect_uri` (or the fallback). If a user is currently logged
  in, the hint's `sub` must match the current user id, otherwise the session is left
  intact.
- **No valid hint + `POST`** → log out and redirect to the fallback. `POST` passes
  through the `web` guard's CSRF protection, so it is same-site.
- **No valid hint + `GET`** → **do not log out**; redirect to the fallback unchanged.

`post_logout_redirect_uri` is only honoured when it is registered on the client the hint
was issued to (stored in `oauth_clients.post_logout_redirect_uris`); otherwise the
fallback (`oidc.logout_redirect`) is used.

Residual risk, accepted by design: `GET /oauth/authorize?max_age=0&client_id=<active client>`
forces re-authentication for an already-authenticated victim when the attacker knows an
active `client_id` (public client ids are discoverable). This is inherent to honouring
`max_age` at the authorization endpoint — the effect is a forced re-login, never account
compromise.

## Assumptions

- The `api` guard uses Passport's `passport` driver.
- `config('passport.guard')` (or, when null, the default guard) identifies the web
  session used for the interactive authorization and logout flows.
- Signing keys are RS256 (`passport:keys`). Token headers carry a `kid` derived from the
  RFC 7638 thumbprint, matched by the JWKS endpoint.

## Octane

The `id_token` response type is resolved once and reused. It clears `nonce`/`auth_time`
after each issuance, so no per-request state leaks into a later token on a long-lived
worker. No `octane:reload` is required for this package; reload only if your own
singletons hold request state.

## Known limitations

- Refresh-grant `id_token`s omit `auth_time` (there is no interactive authentication at
  refresh time), and carry no `nonce`.
- No front-channel or back-channel logout — only RP-initiated (front-channel initiation)
  logout is implemented.
- Introspection/revocation authenticate the client and return the RFC-shaped body
  (`{"active": false}` for unknown/inactive tokens; `401` with `WWW-Authenticate: Basic`
  for failed client authentication). `sub`/`exp` are omitted when absent.

## License

MIT.
