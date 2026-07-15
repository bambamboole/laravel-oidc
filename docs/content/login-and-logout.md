---
title: Login & logout
description: The Authorization Code + PKCE flow in detail, id_token validation, the user-resolution seam, and RP-initiated logout.
sidebar:
  order: 4
---

## Starting a login (`GET login`)

`GET /login` redirects to the provider's authorization endpoint with `response_type=code`,
the configured `client_id`, `redirect_uri`, and `scopes`, plus three one-time values stored
in the session: a `state`, a `nonce`, and a PKCE `code_verifier` (sent as an `S256`
`code_challenge`). An already-authenticated user is redirected straight to
`redirect_after_login` instead.

## The callback (`GET login.callback`)

The callback **pulls** the stored `state`/`nonce`/`code_verifier` from the session — each
callback context is single-use, so a replayed or duplicated callback fails. It then:

1. Rejects the request if the provider returned an `error`, the `state` doesn't match
   (constant-time comparison), or no `code` is present.
2. Exchanges the code at the token endpoint with the `code_verifier` (and the
   `client_secret`, when configured).
3. Validates the returned `id_token` (below).
4. Resolves the local user and logs them into `login_guard`.
5. Stores the token set in the session (`oidc-client.tokens`: `access_token`,
   `refresh_token`, `id_token`) and **regenerates the session id**.
6. Redirects via `redirect()->intended(...)` to `redirect_after_login`.

Any failure redirects back to the `login` route with a generic `oidc` error message —
details never leak to the browser.

## `id_token` validation

The token is validated strictly, in this order:

- Parses as a signed JWT with a `kid` header.
- **Signature** — RS256 against the provider's JWKS key matching `kid`. An unknown `kid`
  triggers one fresh JWKS fetch before failing, so provider key rotation is picked up
  without redeploying.
- **`iss`** equals the configured issuer (trailing slashes ignored).
- **`sub`** is a non-empty string.
- **`aud`** contains this `client_id`; with multiple audiences, `azp` must equal it.
- **`nonce`** equals the one-time nonce from the session.
- **`exp` / `nbf` / `iat`** hold within the configured `leeway`.

## Resolving the local user

By default, the token's `sub` is fed to the login guard's user provider via
`retrieveById($sub)` — which fits self-SSO setups where the provider and client share user
ids. Against a third-party IdP, the `sub` is the *provider's* identifier, so bind your own
resolver in a service provider's `boot()`:

```php
use Bambamboole\LaravelOidcClient\Facades\OidcClient;

OidcClient::resolveUsersUsing(function (string $sub, array $claims): ?User {
    return User::firstOrCreate(
        ['oidc_sub' => $sub],
        ['name' => $claims['name'] ?? '', 'email' => $claims['email'] ?? ''],
    );
});
```

Return `null` to reject the login — the callback fails with the generic error message.

## Logout (`POST logout`)

`POST /logout` logs the user out of `login_guard`, invalidates the session, and — when the
provider advertises an `end_session_endpoint` — redirects there with the session's
`id_token` as `id_token_hint` and the configured `post_logout_redirect_uri`. If discovery
fails or the provider has no end-session endpoint, the user is simply redirected to `/`.

If the provider is `laravel-oidc`, the hint satisfies its
[logout threat model](/provider/logout/) — the provider only destroys its session when the
request proves intent.
