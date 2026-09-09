---
title: Custom claims & triggers
description: Adding claims to access tokens and userinfo responses through supported extension points.
---

Access-token claims are added through capability-scoped triggers registered on the
`Bambamboole\LaravelOidc\Server\Tokens\Pipeline\AccessTokenPipeline` service.
Userinfo claims come from the application's `ClaimsResolver` implementation.

## Access-token triggers

Four access-token triggers are available; register one with
`app(AccessTokenPipeline::class)->register($kind, $callback)`:

| Kind | Fires on | Read context |
| --- | --- | --- |
| `client_credentials` | `client_credentials` grant | `ClientCredentialsEvent` — `client` and finalized `scopes` |
| `token_exchange` | RFC 8693 token exchange | `TokenExchangeEvent` — `user`, `client`, finalized `scopes`, `audience`, and `subjectClaims` |
| `personal_access_token` | Personal access tokens | `PersonalAccessTokenEvent` — `user`, `client`, and finalized `scopes` |
| `authorization_code` | `authorization_code` grant and every `refresh_token` reissue | `AuthorizationCodeEvent` — `user`, `client`, finalized `scopes`, and `grantType` |

Each callback also receives an `AccessTokenApi`. Use `setAccessTokenClaim()` to add a custom claim,
or `deny()` to stop issuance before the access token is persisted. Triggers run once per issuance in
registration order and fail closed when a callback throws.

```php
use Bambamboole\LaravelOidc\Server\Tokens\Pipeline\AccessTokenApi;
use Bambamboole\LaravelOidc\Server\Tokens\Pipeline\AccessTokenPipeline;
use Bambamboole\LaravelOidc\Server\Tokens\Pipeline\ClientCredentialsEvent;

app(AccessTokenPipeline::class)->register('client_credentials', function (ClientCredentialsEvent $event, AccessTokenApi $api): void {
    $api->setAccessTokenClaim('tenant', $event->client->client_id);
});
```

For interactive access-token claims computed once at login, register a hook on `PostLoginPipeline` and call
`LoginApi::setAccessTokenClaim()`. The authentication context carries those claims onto the
authorization-code access token and reissues them through refresh. The same
[post-login pipeline](/auth/post-login-pipeline/) handles login decisions and `id_token` claims.
The `authorization_code` trigger complements it for claims that must be re-evaluated on every issuance;
its claims are stamped after the context's, so a trigger can override a stale login-time claim.

## Userinfo claims

The userinfo endpoint returns `sub` plus whatever `ClaimsResolver::resolve()` returns for a
`ClaimsRequest` carrying `ClaimsAudience::Userinfo`, the token's client and its granted scopes.
Bind a custom implementation of `Bambamboole\LaravelOidc\Server\Scopes\Claims\ClaimsResolver` to add
application-specific claims to userinfo and ID tokens — see
[Scopes & claims](/provider/scopes-and-claims/).

## Protected claims

`AccessTokenApi::setAccessTokenClaim()` refuses protocol-owned access-token claims such as `iss`,
`sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `sid`, `client_id`, `scope`, `scopes`, `cnf`, and `act`.
RFC 8693 actor chains remain owned by the package.
