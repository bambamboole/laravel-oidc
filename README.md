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
- **PKCE (`code_challenge`) is required on every authorization request**, per OAuth 2.1
  §4.1.1/§7.6 — for confidential clients as well as public ones (league's default only
  mandates it for public clients). A request missing it is rejected with `invalid_request`.
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
| UserInfo | `GET\|POST /oauth/userinfo` | Claims for the bearer token (guard: `oidc.api_guard`, default `api`; requires `openid`) |
| End session | `GET\|POST /oauth/logout` | RP-initiated logout (see threat model) |
| Introspection | `POST /oauth/introspect` | RFC 7662 token introspection (client-authenticated) |
| Revocation | `POST /oauth/revoke` | RFC 7009 token revocation (client-authenticated) |

The `/oauth/*` paths above shift with `config('passport.path', 'oauth')`. The discovery
document advertises `response_types_supported: ["code"]`, `response_modes_supported: ["query"]`,
`grant_types_supported` (`authorization_code`, `refresh_token`, `client_credentials`, plus the
device-code and token-exchange URNs when those grants are enabled),
`claims_parameter_supported`, `request_parameter_supported`, and
`request_uri_parameter_supported` all `false`, `code_challenge_methods_supported: ["S256"]`, and
`introspection_endpoint_auth_methods_supported` / `revocation_endpoint_auth_methods_supported`
(`client_secret_basic`, `client_secret_post`) when those endpoints are enabled. Every URL in the
document is built from the configured `issuer` origin, not the incoming request's host.

Each of the last four can be toggled off via config.

## Configuration (`config/oidc.php`)

| Key | Default | Description |
| --- | --- | --- |
| `issuer` | `env('OIDC_ISSUER')` | Issuer URL. Falls back to `app.url` when null. All endpoint URLs advertised in discovery are derived from this origin. |
| `id_token_ttl` | `3600` | `id_token` lifetime in seconds. |
| `endpoints.userinfo` | `true` | Register the userinfo endpoint. |
| `endpoints.end_session` | `true` | Register the logout endpoint. |
| `endpoints.introspection` | `true` | Register the introspection endpoint. |
| `endpoints.revocation` | `true` | Register the revocation endpoint. |
| `api_guard` | `env('OIDC_API_GUARD', 'api')` | The guard the userinfo endpoint authenticates against. |
| `claims_supported` | standard set | Advertised in discovery. |
| `additional_public_keys` | `[]` | Extra PEM public keys to publish in JWKS (key rotation). |
| `logout_redirect` | `/` | Fallback redirect after logout. |

The `/oauth/*` routes this package registers (see [Endpoints](#endpoints)) are mounted under
`config('passport.path', 'oauth')`, so changing Passport's route prefix moves this package's
routes with it.

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
| Access token | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `client_id`, `scope`, `scopes`, `cnf`, `act` |
| Userinfo | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti` |

```php
use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\PostLoginContext;

Oidc::onPostLogin(function (PostLoginContext $context): void {
    $context->idToken->set('department', $context->user->department);
    $context->accessToken->set('tenant', $context->user->tenant_id);
});
```

> **Hooks must be pure claim writers.** A hook is a side-effect-free function that
> only writes claims onto the artifact's `ClaimsBag`. The `PostLogin` and `Refresh`
> triggers run their hooks **more than once** per issuance — once when the access
> token is serialized (via `AccessTokenHookRunner`) and again when the `id_token` is
> serialized (via `IdTokenBuilder`), each time with the sibling artifact's bag
> discarded. Each artifact's writer is applied only when that artifact is serialized.
> Because a hook may be invoked multiple times per request, it must be idempotent: do
> **not** perform audit logging, increment counters, or write to the database inside a
> hook. For side effects that must happen once per authentication, use Laravel's auth
> events (e.g. `Illuminate\Auth\Events\Login`) instead.

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
subject token**, and a subject token that is **not bound to a user** (e.g. a
client-credentials token whose `sub` is a client id), with `invalid_grant` before the
policy ever runs — the exchange design assumes a user subject.

### Guarantees

- No refresh token is ever issued from this grant.
- The exchanged token's subject (`sub`) is always identical to the subject token's.
- The exchanged token's lifetime never exceeds the subject token's remaining lifetime.
- Revoking a subject token does **not** cascade to already-issued exchanged tokens:
  each exchanged token remains valid until its own `exp` (capped at the subject's
  `exp`) or until it is independently revoked.

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

## Powering a remote / browser-fetch flow

The package uses a **two-token model** to let a browser call downstream resource
servers without ever holding a long-lived, broadly-scoped credential:

1. A first-party **session root token** — an RFC 9068 access token minted for the
   logged-in user and kept server-side in the Laravel session (never sent to the
   browser). It is established by a `SessionTokenProvider` at login and re-minted
   on demand as it nears expiry.
2. Per-audience **browser tokens** — short-lived, narrowly-scoped access tokens
   exchanged from the root token via RFC 8693 token exchange, one per resource
   server the browser needs to call. These are the only tokens handed to the client.

> With `SESSION_DRIVER=cookie` the root token rides inside the encrypted session
> cookie sent to the browser; a server-side session driver (e.g. `database`,
> `redis`) is recommended so the root token stays server-side.

### Config

| Key | Default | Description |
| --- | --- | --- |
| `oidc.first_party_client` | `env('OIDC_FIRST_PARTY_CLIENT')` | The confidential client id used to mint the session root token and to perform exchanges on its behalf. Its `allowed_exchange_audiences` (see [Token exchange](#token-exchange-rfc-8693)) gates which audiences `issueScopedToken()` may mint for. |
| `oidc.session_token.ttl` | `3600` (`OIDC_SESSION_TOKEN_TTL`) | Root token lifetime in seconds. |
| `oidc.session_token.session_key` | `oidc.session_token` | Session key the root token (JWT, `jti`, `expires_at`) is stored under. |
| `oidc.session_token.refresh_skew` | `60` | Seconds before expiry at which `currentToken()` re-mints instead of reusing the stored token. |
| `oidc.session_token.scopes` | `null` | Scopes granted to the root token. `null` grants every non-hidden scope in the `ScopeRepository`; set an array to restrict it. |

### The `SessionTokenProvider` seam

`Bambamboole\LaravelOidc\Contracts\SessionTokenProvider` (`currentToken(): ?string`,
`establish(Authenticatable $user): void`, `forget(): void`) is bound by default to
`SessionMintTokenProvider`, which mints the root token on the `Login` event
(`EstablishSessionToken`), re-mints it lazily from `currentToken()` once it is within
`refresh_skew` seconds of expiry, and revokes + clears it on the `Logout` event
(`ForgetSessionToken`). Rebind the contract to change *how* the root token is
obtained — e.g. sourcing it from a self-RP or an external SSO exchange — without
touching any caller of `Oidc::issueScopedToken()`:

```php
$this->app->singleton(SessionTokenProvider::class, MyExternalSsoTokenProvider::class);
```

### Issuing a browser token

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

$issued = Oidc::issueScopedToken('https://api.orders.test', ['openid']);
```

`issueScopedToken(string $audience, array $scopes): IssuedToken` reads the current
session root token, exchanges it (in-process, via the same RFC 8693 grant logic used
by `/oauth/token`) for a token scoped to `$audience`, and returns an `IssuedToken`:

```php
final readonly class IssuedToken
{
    public string $accessToken;
    public string $tokenType;   // "Bearer"
    public int $expiresIn;      // seconds remaining
    public string $audience;
    /** @var string[] */
    public array $scopes;
}
```

It throws a `RuntimeException` if there is no session root token for the current
user, or if `oidc.first_party_client` is unset or does not resolve to a client. The
usual `DefaultExchangePolicy` rules apply — the requested audience must be in the
first-party client's `allowed_exchange_audiences`, and requested scopes must be a
subset of the root token's scopes.

### Validating browser tokens on the resource server

The resource server never sees the session root token — only the audience-scoped
`accessToken` from `IssuedToken`, an RFC 9068 `at+jwt`. Validate it one of three ways:

- **JWKS (stateless).** Fetch `GET /.well-known/openid-configuration`, follow
  `jwks_uri`, verify the token's signature against the matching key (`kid`), and check
  `iss` matches the issuer, `aud` contains your resource server's audience, `exp` is in
  the future, and the header `typ` is `at+jwt`. No call back to the OP per request.
- **Introspection (revocation-aware).** `POST /oauth/introspect` with the resource
  server's own client credentials and the token as `token`. Returns
  `{"active": true, ...}` or `{"active": false}` — catches tokens revoked before
  their `exp`, at the cost of a round trip per check.
- **Same-app resource server.** If the RS lives in this same Laravel app, use the
  `CheckAudience` middleware instead of hand-rolling either of the above — it already
  performs signature, `typ`, expiry, and revocation checks against this package's own
  JWKS and token store:

  ```php
  Route::middleware(CheckAudience::using('https://api.orders.test'))
      ->get('/orders', fn (Request $request) => ...);
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
  (`{"active": false}` for unknown/inactive tokens; a `401` with `WWW-Authenticate: Basic
  realm="OIDC"` and an RFC 6749 §5.2 `{"error": "invalid_client"}` JSON body for failed client
  authentication). `sub`/`exp` are omitted when absent.
- The userinfo endpoint and the `CheckAudience` middleware return RFC 6750 bearer-token error
  responses on failure: a `WWW-Authenticate: Bearer error="..."` header plus a JSON
  `{"error": "invalid_token"}` (missing/expired/revoked/malformed token) or
  `{"error": "insufficient_scope"}` (valid token, wrong scope/audience) body, instead of a bare
  `401`/`403`.

## Releasing

The package is developed inside the [saas-starter-kit](https://github.com/bambamboole/saas-starter-kit)
monorepo under `packages/laravel-oidc` and mirrored to this repository. To cut a release:

```bash
# from the monorepo root, split the package's history to a standalone branch
git subtree split --prefix=packages/laravel-oidc -b oidc-release

# push it to this repository's main
git push git@github.com:bambamboole/laravel-oidc.git oidc-release:main

# tag the release (on the mirror), then submit/enable auto-update on Packagist
git tag v0.1.0 && git push git@github.com:bambamboole/laravel-oidc.git v0.1.0
```

CI (`.github/workflows/tests.yml`) runs the suite across Laravel 11/12/13 on every push and pull
request to this repository.

## License

MIT.
