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

## Custom claims

Register a hook to inject claims into an issued artifact (an `id_token`, an access
token, or a userinfo response) without replacing the `ClaimsResolver`. Hooks are
registered against the `Oidc` facade, typically in a service provider `boot()`, and
run at one of five triggers:

| Trigger | Fires on | Context |
| --- | --- | --- |
| `Oidc::onPostLogin()` | `authorization_code` grant (interactive login) | `PostLoginContext` — `user`, `client`, `grantedScopes`, `nonce`, `authTime`, and writers for `idToken` and `accessToken` |
| `Oidc::onRefresh()` | `refresh_token` grant | `RefreshContext` — `user`, `client`, `grantedScopes`, and writers for `idToken` and `accessToken` (there is no `id_token` `nonce`/`auth_time` on refresh) |
| `Oidc::onClientCredentials()` | `client_credentials` grant | `ClientCredentialsContext` — `client`, `grantedScopes`, and a writer for `accessToken` |
| `Oidc::onTokenExchange()` | RFC 8693 token exchange (Phase 2) | `TokenExchangeContext` — `user`, `client`, `grantedScopes`, `audience`, `subjectClaims`, and a writer for `accessToken` |
| `Oidc::onUserinfo()` | `GET\|POST /oauth/userinfo` | `UserinfoContext` — `user`, `client`, `grantedScopes`, and a writer for `claims` |

Each context exposes one `ClaimsBag` per artifact it can write to (`$context->idToken`,
`$context->accessToken`, `$context->claims`). Call `->set($name, $value)` to add a
claim; a hook that throws is caught, logged, and skipped rather than failing the
request.

`ClaimsBag::set()` silently drops (and logs a warning for) any claim name the
artifact controls itself, so hooks cannot forge protocol claims:

| Artifact | Protected claims |
| --- | --- |
| `id_token` | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `nonce`, `at_hash`, `c_hash`, `auth_time`, `azp`, `acr`, `amr` |
| Access token | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `client_id`, `scope`, `scopes`, `cnf` |
| Userinfo | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti` |

```php
use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\PostLoginContext;

Oidc::onPostLogin(function (PostLoginContext $context): void {
    $context->idToken->set('department', $context->user->department);
    $context->accessToken->set('tenant', $context->user->tenant_id);
});
```

These hooks only fire while issuing OIDC artifacts. User-lifecycle events —
registration, email verification, password resets — are not part of this package;
handle them where the rest of the app's authentication lives (Fortify).

## Access token format (RFC 9068)

Access tokens issued by this package are structured JWTs per
[RFC 9068](https://www.rfc-editor.org/rfc/rfc9068) rather than opaque strings:

- The JWT header carries `"typ": "at+jwt"` and a `kid` matching the JWKS endpoint.
- Standard claims: `iss`, `aud`, `sub`, `client_id`, `iat`, `nbf`, `exp`, `jti`, and a
  space-delimited `scope` string (e.g. `"openid email"`).
- The legacy `scopes` array claim (`["openid", "email"]`) is retained alongside
  `scope` because Passport's `auth:api` guard and this package's userinfo endpoint
  read it — dropping it would break authentication.
- `aud` defaults to the requesting client's id. It is overridden by RFC 8693 token
  exchange (Phase 2), which sets it to the requested `resource`/`audience` instead.

## Token exchange (RFC 8693)

The provider optionally supports [RFC 8693](https://www.rfc-editor.org/rfc/rfc8693)
token exchange: a confidential client trades a token it already holds for a new
access token scoped to a different `aud` (audience), typically to call a downstream
resource server. It is gated behind `config('oidc.token_exchange.enabled')` (default
`true`; `OIDC_TOKEN_EXCHANGE_ENABLED`), advertised in discovery's
`grant_types_supported` when enabled, and registered under the grant identifier:

```
urn:ietf:params:oauth:grant-type:token-exchange
```

### Enabling it per client

A client must opt in on two columns of `oauth_clients` (added by this package's
migration):

```php
$client->forceFill([
    'grant_types' => [...$client->grant_types, 'urn:ietf:params:oauth:grant-type:token-exchange'],
    'allowed_exchange_audiences' => json_encode(['https://api.internal/orders']),
])->save();
```

- `grant_types` must include the exchange URN, or the token endpoint rejects the
  client with `invalid_client`.
- `allowed_exchange_audiences` is a JSON array of audience strings the client is
  permitted to request. Anything outside it is rejected (see below).

### Request and response

```
POST /oauth/token
grant_type=urn:ietf:params:oauth:grant-type:token-exchange
client_id=...
client_secret=...
subject_token=<access_token to exchange>
subject_token_type=urn:ietf:params:oauth:token-type:access_token
audience=https://api.internal/orders
scope=orders:read          # optional
```

Only `subject_token_type=urn:ietf:params:oauth:token-type:access_token` is
supported (and, if given, `requested_token_type` must also be the access-token URN);
anything else is rejected with `invalid_request`.

```json
{
    "access_token": "eyJ...",
    "issued_token_type": "urn:ietf:params:oauth:token-type:access_token",
    "token_type": "Bearer",
    "scope": "orders:read"
}
```

There is **no `refresh_token`** in the response — the grant never mints one. The
issued access token is the same RFC 9068 `at+jwt` format described above, with `aud`
set to the requested `audience` and an `act` claim (`{"client_id": "..."}`)
identifying the exchanging client as the actor.

### The `ExchangePolicy` contract

Every exchange request is authorized by `Bambamboole\LaravelOidc\Contracts\ExchangePolicy`,
bound by default to `DefaultExchangePolicy`:

```php
namespace Bambamboole\LaravelOidc\Contracts;

use Bambamboole\LaravelOidc\Exchange\ExchangeGrantResult;
use Bambamboole\LaravelOidc\Exchange\ExchangeRequest;

interface ExchangePolicy
{
    public function authorize(ExchangeRequest $request): ExchangeGrantResult;
}
```

`ExchangeRequest` carries the requesting `client`, the subject token's decoded
`subjectClaims`, the requested `audience`/`scopes`, and the subject token's
`subjectExpiresAt`. `authorize()` must either return an `ExchangeGrantResult`
(`userId`, `scopes`, `audience`, `expiresAt`) or throw a
`League\OAuth2\Server\Exception\OAuthServerException` to fail the exchange with a
specific RFC-shaped error. Replace the default to add tenant checks, custom scope
rules, or a different allowlist source:

```php
use Bambamboole\LaravelOidc\Contracts\ExchangePolicy;
use Bambamboole\LaravelOidc\Exchange\ExchangeGrantResult;
use Bambamboole\LaravelOidc\Exchange\ExchangeRequest;
use League\OAuth2\Server\Exception\OAuthServerException;

class TenantScopedExchangePolicy implements ExchangePolicy
{
    public function authorize(ExchangeRequest $request): ExchangeGrantResult
    {
        if (($request->subjectClaims['tenant_id'] ?? null) !== $request->client->tenant_id) {
            throw OAuthServerException::accessDenied('Cross-tenant exchange is not permitted.');
        }

        // ... reuse or reimplement the reciprocity/allowlist/scope checks below ...

        return new ExchangeGrantResult(
            userId: (string) $request->subjectClaims['sub'],
            scopes: $request->requestedScopes ?? [],
            audience: [$request->requestedAudience],
            expiresAt: $request->subjectExpiresAt,
        );
    }
}
```

```php
$this->app->singleton(ExchangePolicy::class, TenantScopedExchangePolicy::class);
```

### `DefaultExchangePolicy` rules

1. **Audience reciprocity.** The requesting client must be the one the subject token
   was issued to or for: either the client's id is present in the subject token's
   `aud`, or the subject token's `client_id` claim matches. Otherwise `access_denied`.
2. **Target allowlist.** The requested `audience` must be one of the requesting
   client's `allowed_exchange_audiences`. Otherwise `invalid_target` (400).
3. **Scope narrowing.** Requested scopes (defaulting to the subject token's own
   scopes when `scope` is omitted) must be a subset of the subject token's scopes —
   exchange can only narrow, never widen. A scope not held by the subject token
   fails with `invalid_scope`.
4. **Same subject.** The exchanged token's `sub` is always the subject token's own
   `sub` claim. There is no actor/impersonation parameter — a client can narrow and
   re-target its own subject's token, not mint a token for a different user.
5. **Lifetime cap.** The exchanged token's expiry is capped at the *earlier* of the
   server's configured access-token TTL and the subject token's own `exp`; an
   exchanged token never outlives the token it was derived from.

Independently of the policy, the grant itself rejects an **expired or revoked
subject token** with `invalid_grant` before the policy ever runs.

### Guarantees

- No refresh token is ever issued from this grant.
- The exchanged token's subject (`sub`) is always identical to the subject token's.
- The exchanged token's lifetime never exceeds the subject token's remaining lifetime.

### `CheckAudience` — the resource-server middleware

`Bambamboole\LaravelOidc\Http\Middleware\CheckAudience` is a **self-contained**
RFC 9068 resource-server validator for routes that accept exchanged (or any
audience-scoped) tokens. It does *not* need — and must **not** be paired with —
`auth:api`: an exchanged token's `aud` is a resource audience, not a client id, and
Passport's `auth:api` guard would reject it.

It independently validates, in order: the bearer token's signature (against this
package's JWKS), that its header `typ` is `at+jwt`, that it is not expired, that it
is not revoked (checked against the OP's own token store), and that its `aud`
intersects the audiences the route requires. On success it resolves the request's
user from the token's `sub` claim via the guard's user provider — no `Auth::` call
needed on the route.

```php
use Bambamboole\LaravelOidc\Http\Middleware\CheckAudience;
use Illuminate\Support\Facades\Route;

Route::middleware(CheckAudience::using('https://api.internal/orders'))
    ->get('/orders', fn (Request $request) => response()->json([
        'user' => $request->user()?->getAuthIdentifier(),
    ]));
```

`CheckAudience::using(...$audiences)` accepts one or more audiences; the request
passes if the token's `aud` intersects any of them. A missing/malformed/expired/
revoked/wrong-`typ` token aborts with `401`; a valid token whose `aud` doesn't match
aborts with `403`.

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
