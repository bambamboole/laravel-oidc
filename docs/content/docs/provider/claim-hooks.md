---
title: Custom claims (hooks)
description: Injecting claims into issued artifacts with claim hooks.
---

Register a hook to inject claims into an issued artifact — an access token or a userinfo
response — without replacing the `ClaimsResolver`. Hooks are registered against the `Oidc` facade,
typically in a service provider `boot()`.

## The three claim hooks

There are exactly three claim hooks:

| Hook | Fires on | Context |
| --- | --- | --- |
| `Oidc::onClientCredentials()` | `client_credentials` grant | `ClientCredentialsContext` — `client`, `grantedScopes`, and a writer for `accessToken` |
| `Oidc::onTokenExchange()` | RFC 8693 token exchange | `TokenExchangeContext` — `user`, `client`, `grantedScopes`, `audience`, `subjectClaims`, and a writer for `accessToken` |
| `Oidc::onUserinfo()` | `GET\|POST /oauth/userinfo` | `UserinfoContext` — `user`, `client`, `grantedScopes`, and a writer for `claims` |

For injecting claims at interactive login, these hooks are **not** the mechanism — use the
post-login pipeline instead, described under [Post-login pipeline](/auth/post-login-pipeline/).

## Writing claims

Each context exposes one `ClaimsBag` per artifact it can write to (`$context->accessToken` or
`$context->claims`). Call `->set($name, $value)` to add a claim. A hook that throws is caught,
logged, and skipped rather than failing the request.

```php
use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\ClientCredentialsContext;

Oidc::onClientCredentials(function (ClientCredentialsContext $context): void {
    $context->accessToken->set('tenant', $context->client->getIdentifier());
});
```

## Protected claims

`ClaimsBag::set()` silently drops (and logs a warning for) any claim name the artifact controls
itself, so a hook can never forge a protocol claim:

| Artifact | Protected claims |
| --- | --- |
| `id_token` | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `nonce`, `at_hash`, `c_hash`, `auth_time`, `azp`, `acr`, `amr` |
| Access token | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `client_id`, `scope`, `scopes`, `cnf`, `act` |
| Userinfo | `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti` |

## Hooks must be pure claim writers

A claim hook is a side-effect-free function that only writes claims onto the artifact's
`ClaimsBag`. Because a hook **may run more than once** per issuance — the access-token bag is
built whenever the token is serialized — it must be **idempotent**. Do **not** perform audit
logging, increment counters, or write to the database inside a hook. For side effects that must
happen once per authentication, use Laravel's auth events (e.g.
`Illuminate\Auth\Events\Login`), or the post-login pipeline.

These hooks only fire while issuing OIDC artifacts. User-lifecycle events — registration, email
verification, password resets — are handled elsewhere in the app's authentication layer.

The access-token claims written here ride on the RFC 9068 `at+jwt` — see
[Access tokens](/provider/access-tokens/) for that format.
