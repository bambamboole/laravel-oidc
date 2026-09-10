---
title: Resource servers (CheckAudience)
description: Validating audience-scoped access tokens on a resource server — JWKS, introspection, or the auth:oidc guard paired with CheckAudience.
---

A resource server receives an audience-scoped RFC 9068 `at+jwt` access token (for
example the `accessToken` from an [`IssuedToken`](/advanced/browser-fetch/)) and must
validate it before serving the request. There are three ways to do that.

## The `auth:oidc` guard

If the resource server *is* this same app, use the `auth:oidc` guard (auto-registered under the
guard name in `oidc.auth.api_guard`, `oidc` by default — see [Configuration](/introduction/configuration/)).
It's a self-contained RFC 9068 resource-server validator: signature, `iss`, `at+jwt` `typ`,
expiry, and revocation, all checked against this package's own JWKS and token store. It accepts a
bearer token only when its `aud` names the realm issuer URL or a resource registered under
`oidc.resources` (RFC 9068 §4). A token minted without an RFC 8707 `resource` is addressed to the
issuer alone, so a classic authorization-code token passes; a token addressed to some other
resource server, to a client id, or a revoked token 401s regardless of which client it was issued
to. To obtain a token for a registered resource, the client names it with `resource` at the
authorization endpoint or exchanges its token for one — see [Access tokens](/provider/access-tokens/).

This makes `auth:oidc` usable directly on routes that only need *a* valid authenticated user at
one of the realm's resources. Pair it with `CheckAudience` — see below — when a route must enforce
a *specific* audience, not just any recognized one.

See the [API token broker](/client/api-token-broker/) for the client-side half of this contract —
the audience it requests must match what a route here accepts, and be listed in the requesting
client's `allowed_exchange_audiences`.

## Three validation options

- **JWKS (stateless).** Fetch `GET /.well-known/openid-configuration`, follow
  `jwks_uri`, verify the token's signature against the matching key (`kid`), and check
  that `iss` matches the issuer, `aud` contains your resource server's audience, `exp`
  is in the future, and the header `typ` is `at+jwt`. No call back to the OP per
  request — but it cannot see a token revoked before its `exp`.
- **Introspection (revocation-aware).** `POST /oauth/introspect` with the resource
  server's own client credentials and the token as `token`. Returns
  `{"active": true, ...}` or `{"active": false}` — catches tokens revoked before their
  `exp`, at the cost of a round trip per check.
- **Same-app resource server.** If the resource server lives in this same Laravel app,
  pair the `auth:oidc` guard with the `CheckAudience` middleware instead of hand-rolling
  either of the above — `auth:oidc` performs signature, `typ`, expiry, and revocation
  checks against this package's own JWKS and token store, and `CheckAudience` narrows
  the accepted token to a specific audience.

## `CheckAudience`

`Bambamboole\LaravelOidc\Server\Tokens\Http\Middleware\CheckAudience` narrows an already-authenticated
request to a specific audience. It performs no signature, `typ`, expiry, or revocation checks of
its own — that's `auth:oidc`'s job.

:::danger[Must be paired with `auth:oidc`]
`CheckAudience` must run **after** `auth:oidc` (or any guard populating
`$request->user()->currentAccessToken()`). It reads the audience the guard already verified from a
request attribute rather than re-parsing the token. Placed without a preceding guard, it rejects
every request with `401 invalid_token` — there is no authenticated user to read an audience from.
:::

It validates, **in order**:

1. That `$request->user()` is an authenticated `OAuthenticatable` with a `currentAccessToken()` —
   otherwise `401 invalid_token`.
2. That the audience `auth:oidc` verified intersects the audiences the route requires —
   otherwise `401 invalid_token` (RFC 6750 §3.1: a token for another resource is not a token short
   of a scope).

```php
use Bambamboole\LaravelOidc\Server\Tokens\Http\Middleware\CheckAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:oidc', CheckAudience::using('https://api.internal/orders')])
    ->get('/orders', fn (Request $request) => response()->json([
        'user' => $request->user()?->getAuthIdentifier(),
    ]));
```

`CheckAudience::using(...$audiences)` accepts one or more audiences; the request passes
if the audience `auth:oidc` verified intersects any of them.

Because `auth:oidc` checks revocation against the OP's own token store, this pairing suits a
resource server that shares (or is) the OP. A fully external resource server should validate via
token introspection instead.

## Failure semantics

A request turned away by `auth:oidc` is answered with an RFC 6750 §3 Bearer challenge rather than
Laravel's generic `Unauthenticated.` response. The package registers the renderable for
`Illuminate\Auth\AuthenticationException` itself, for the guard named by `oidc.auth.api_guard` and any
other guard using the `oidc` driver. `CheckAudience` and `CheckScopes`, layered after it, render
the errors for the checks they own:

| Condition | Status | Body |
| --- | --- | --- |
| No bearer token presented | `401` | empty — RFC 6750 §3.1 omits the error code when no credentials were sent |
| A bearer token the guard rejected (signature, `iss`, `typ`, expiry, revocation, audience, unknown user) | `401` | `{"error": "invalid_token"}` |
| `CheckAudience`: the guard-verified audience does not intersect the audiences `CheckAudience::using()` requires | `401` | `{"error": "invalid_token"}` — a token for another resource is not a valid token here (RFC 6750 §3.1) |
| `CheckAudience` or `CheckScopes` without a preceding guard populating `currentAccessToken()` | `401` | `{"error": "invalid_token"}` |
| `CheckScopes`: the token lacks a required scope | `403` | `{"error": "insufficient_scope"}` |

Every challenge is a `WWW-Authenticate: Bearer` header carrying `realm` (the realm id), `error`
where one applies, and `resource_metadata` — the URL of the realm's RFC 9728 protected resource
metadata (`/.well-known/oauth-protected-resource`) — as RFC 9728 §5.1 prescribes,
so a client that lands on a protected route without a token can discover the authorization server
from the challenge alone:

```text
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer realm="default", error="invalid_token", resource_metadata="https://id.example.com/.well-known/oauth-protected-resource"
```
