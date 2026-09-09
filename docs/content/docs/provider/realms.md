---
title: Realms
description: How the package serves several realms from one deployment, and the one piece of isolation your application has to provide.
---

The package serves several **realms** — isolated sets of clients, tokens, sessions and signing
keys — from one deployment. Each realm is its own OpenID Provider: it has its own issuer, its own
discovery document and its own signing keys.

It does **not** define what a realm is. There is no realm table here, no foreign key, and no
administration. A realm is an opaque string; its name, branding and administrators belong to your
application, exactly as the user model does.

## Addressing

Every endpoint lives below `/realms/{realm}`:

```
https://id.example.com/realms/acme/.well-known/openid-configuration
https://id.example.com/realms/acme/oauth/authorize
https://id.example.com/realms/acme/auth/login
```

The issuer follows from it — `oidc.issuer` (or `app.url`) supplies the origin, and the realm
supplies the path:

```
issuer = https://id.example.com/realms/acme
```

So a relying party configured against `https://id.example.com/realms/acme` discovers, verifies
and logs in entirely within one realm.

### Metadata that is not prefixed

RFC 8414 and RFC 9728 build their metadata URLs by inserting the well-known segment *ahead* of
the issuer's path rather than appending it, so those two sit above the prefix:

```
https://id.example.com/.well-known/oauth-authorization-server/realms/acme
https://id.example.com/.well-known/oauth-protected-resource/realms/acme/mcp
```

OpenID Connect Discovery appends instead, which is why
`/realms/acme/.well-known/openid-configuration` is the prefixed one. Both serve the same
document.

## Resolving the realm

Everything hangs off one contract:

```php
namespace Bambamboole\LaravelOidc\Server\Realms;

interface RealmResolver
{
    public function current(): string;
}
```

The default `RouteRealmResolver` reads the `{realm}` route parameter and falls back to
`config('oidc.realm')` when there is no matched route — console commands, queued jobs. Bind your
own to derive it differently:

```php
use Bambamboole\LaravelOidc\Server\Realms\RealmResolver;

$this->app->scoped(RealmResolver::class, fn (): RealmResolver => new MyRealmResolver);
```

Bind it **scoped**, not as a singleton — the realm is per request. Anything longer-lived than a
request must call `current()` per use rather than hold the resolver; the signing key store does
exactly that.

### ResolveRealm

The `ResolveRealm` middleware runs on every package route and does three things: it records the
realm on the request, registers it as the default `{realm}` for URL generation, and scopes the
session cookie to `/realms/{realm}`.

It also removes `{realm}` from the matched route's parameters. That is load-bearing rather than
cosmetic: Laravel hands route parameters to controller methods positionally, so a leading realm
would shift every other argument along by one.

### URL generation outside a request

`route('oidc.authorize')` needs a realm. During a request `ResolveRealm` supplies the matched
one. Outside a request the default is `config('oidc.realm')`, registered at boot. A queued job
that generates URLs for a realm other than that default has to set it itself:

```php
URL::defaults(['realm' => $realm]);
```

This applies to queued password-reset and email-verification notifications in a multi-realm
deployment.

## What the package scopes

`realm_id` is stored on `oidc_clients`, `oidc_access_tokens`, `oidc_auth_codes`, `oidc_sessions`,
`oidc_signing_keys`, `oidc_authentication_contexts` and `oidc_social_accounts`. A `client_id` only
has to be unique **within** its realm, so the same readable name can exist in several.

Refresh tokens carry no realm of their own — they inherit the one of the access token they were
issued alongside.

Scoping is explicit (`Model::query()->inRealm()`), not a global scope: administration reads across
realms on purpose, and a guard that half the callers disable hides the isolation it claims to
provide.

## What your application must scope

:::danger[The package cannot isolate users for you]
It does not own the user model. Two things are yours, and getting them wrong lets somebody sign
into the wrong realm:

1. **The user provider.** `retrieveByCredentials()` must carry a hard realm constraint. Without it,
   a password that is valid in one realm authenticates in every realm.
2. **The uniqueness constraint.** `users.email` must be `unique(realm_id, email)`, not globally
   unique — otherwise a realm can never have a user that exists elsewhere.

Write a test that signs in with realm A's credentials against realm B's URL and asserts a
failure. It is the one bug in this area that stays silent in production.
:::

## Sessions

The session cookie is scoped to `/realms/{realm}`, so a browser session in one realm is not sent
to another. The realm's own SSO session (`oidc_sessions`) and its stashed authorize request are
scoped by `realm_id` on top of that.
