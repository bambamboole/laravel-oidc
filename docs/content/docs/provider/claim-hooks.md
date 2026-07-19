---
title: Custom claims
description: Adding claims to access tokens and userinfo responses through supported extension points.
---

Access-token claims are added through capability-scoped triggers registered on the `Oidc` facade.
Userinfo claims come from the application's `ClaimsResolver` implementation.

## Access-token triggers

Two access-token triggers are available:

| Method | Fires on | Read context |
| --- | --- | --- |
| `Oidc::clientCredentials()` | `client_credentials` grant | `ClientCredentialsEvent` — `client` and finalized `scopes` |
| `Oidc::tokenExchange()` | RFC 8693 token exchange | `TokenExchangeEvent` — `user`, `client`, finalized `scopes`, `audience`, and `subjectClaims` |

Each callback also receives an `AccessTokenApi`. Use `setAccessTokenClaim()` to add a custom claim,
or `deny()` to stop issuance before the access token is persisted. Triggers run once per issuance in
registration order and fail closed when a callback throws.

```php
use Bambamboole\LaravelOidc\Auth\Pipeline\AccessTokenApi;
use Bambamboole\LaravelOidc\Auth\Pipeline\ClientCredentialsEvent;
use Bambamboole\LaravelOidc\Facades\Oidc;

Oidc::clientCredentials(function (ClientCredentialsEvent $event, AccessTokenApi $api): void {
    $api->setAccessTokenClaim('tenant', $event->client->getIdentifier());
});
```

For interactive access-token claims, register `Oidc::postLogin()` and call
`LoginApi::setAccessTokenClaim()`. The authentication context carries those claims onto the
authorization-code access token and reissues them through refresh. The same
[post-login pipeline](/auth/post-login-pipeline/) handles login decisions and `id_token` claims.

## Userinfo claims

The userinfo endpoint returns `sub` plus the result of
`ClaimsResolver::resolve($user)->forScopes($grantedScopes)`. Bind a custom implementation of
`Bambamboole\LaravelOidc\Contracts\ClaimsResolver` to add application-specific, scope-filtered
claims to both userinfo and ID tokens.

## Protected claims

`AccessTokenApi::setAccessTokenClaim()` refuses protocol-owned access-token claims such as `iss`,
`sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `sid`, `client_id`, `scope`, `scopes`, `cnf`, and `act`.
RFC 8693 actor chains remain owned by the package.
