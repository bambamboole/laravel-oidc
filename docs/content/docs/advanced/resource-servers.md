---
title: Resource servers (CheckAudience)
description: Validating audience-scoped access tokens on a resource server — JWKS, introspection, or the auth:oidc guard paired with CheckAudience.
---

A resource server receives an audience-scoped RFC 9068 `at+jwt` access token (for
example the `accessToken` from an [`IssuedToken`](/advanced/browser-fetch/)) and must
validate it before serving the request. There are three ways to do that.

## The `auth:oidc` guard

If the resource server *is* this same app, use the `auth:oidc` guard (auto-registered under the
guard name in `oidc.api_guard`, `oidc` by default — see [Configuration](/introduction/configuration/)).
It's a self-contained RFC 9068 resource-server validator: signature, `at+jwt` `typ`, expiry, and
revocation, all checked against this package's own JWKS and token store. It accepts a bearer token
when its `aud` intersects the issuer URL or an entry in `oidc.resource.audiences`, or the token
carries its own `client_id` claim — the latter is what makes classic (non-exchanged) tokens pass
uniformly, since a classic token's `aud` defaults to `[client_id]`. A token whose `aud` names some
other resource server, or a revoked token, still 401s.

This makes `auth:oidc` usable directly on routes that only need *a* valid authenticated user,
regardless of which audience the token was exchanged for. Pair it with `CheckAudience` — see below —
when a route must enforce a *specific* audience, not just any recognized one.

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

`Bambamboole\LaravelOidc\Server\Http\Middleware\CheckAudience` narrows an already-authenticated
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
   otherwise `403 insufficient_scope`.

```php
use Bambamboole\LaravelOidc\Server\Http\Middleware\CheckAudience;
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

`auth:oidc` itself renders no OAuth-style error — a rejected token just leaves `$request->user()`
null, and standard Laravel guard-middleware behavior (a plain `401`) applies from there.
`CheckAudience`, layered after it, renders RFC 6750-style errors for the checks it owns:

| Condition | Status | Body |
| --- | --- | --- |
| No authenticated user with a `currentAccessToken()` (the guard rejected the token, or didn't run) | `401` | `{"error": "invalid_token"}` |
| Authenticated, but the guard-verified audience doesn't intersect the audiences `CheckAudience::using()` requires | `403` | `{"error": "insufficient_scope"}` |

Both `CheckAudience` responses follow RFC 6750: a `WWW-Authenticate: Bearer error="..."` header
accompanies the JSON body rather than a bare status code.
