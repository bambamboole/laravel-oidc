---
title: Resource servers (CheckAudience)
description: Validating audience-scoped access tokens on a resource server — JWKS, introspection, or the self-contained CheckAudience middleware.
---

A resource server receives an audience-scoped RFC 9068 `at+jwt` access token (for
example the `accessToken` from an [`IssuedToken`](/advanced/browser-fetch/)) and must
validate it before serving the request. There are three ways to do that.

## The `auth:api` guard also accepts resource-audience tokens

If the resource server *is* this same app, Passport's `auth:api` guard already recognizes an
exchanged token: when a bearer token's `aud` names this server — the issuer URL, or an entry in
`oidc.resource.audiences` — the guard resolves the acting client from the token's RFC 9068
`client_id` claim instead of `aud[0]`. A classic (non-exchanged) token, whose `aud` is already the
client id, is unaffected. A token whose `aud` names some other resource server still 401s, and a
revoked exchanged token still 401s, exactly as any revoked token would.

This makes `auth:api` usable directly on routes that only need *a* valid authenticated user,
regardless of which audience the token was exchanged for. Use `CheckAudience` instead — see below —
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
  use the `CheckAudience` middleware instead of hand-rolling either of the above — it
  already performs signature, `typ`, expiry, and revocation checks against this
  package's own JWKS and token store.

## `CheckAudience`

`Bambamboole\LaravelOidc\Server\Http\Middleware\CheckAudience` is a **self-contained** RFC 9068
resource-server validator for routes that accept exchanged (or any audience-scoped)
tokens.

:::danger[Do not pair it with `auth:api`]
`CheckAudience` does *not* need — and must **not** be paired with — `auth:api`. An
exchanged token's `aud` is a resource audience, not a client id, and Passport's
`auth:api` guard would reject it.
:::

It independently validates, **in order**:

1. The bearer token's **signature** (against this package's JWKS).
2. That its header **`typ` is `at+jwt`**.
3. That it is **not expired**.
4. That it is **not revoked** (checked against the OP's own token store).
5. That its **`aud` intersects** the audiences the route requires.

On success it resolves the request's user from the token's `sub` claim via the guard's
user provider and sets it as the request's user — no `Auth::` call needed on the route.
A token whose `sub` does not resolve to a user is rejected with `401 invalid_token`.

```php
use Bambamboole\LaravelOidc\Server\Http\Middleware\CheckAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(CheckAudience::using('https://api.internal/orders'))
    ->get('/orders', fn (Request $request) => response()->json([
        'user' => $request->user()?->getAuthIdentifier(),
    ]));
```

`CheckAudience::using(...$audiences)` accepts one or more audiences; the request passes
if the token's `aud` intersects any of them.

Because revocation is checked against the OP's own token store, `CheckAudience` suits a
resource server that shares (or is) the OP. A fully external resource server should
validate via token introspection instead.

## Failure semantics

| Condition | Status | Body |
| --- | --- | --- |
| Missing / malformed / expired / revoked / wrong-`typ` token | `401` | `{"error": "invalid_token"}` |
| Valid token whose `aud` doesn't match | `403` | `{"error": "insufficient_scope"}` |

Both responses follow RFC 6750: a `WWW-Authenticate: Bearer error="..."` header
accompanies the JSON body rather than a bare status code.
