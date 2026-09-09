---
title: Realms
description: How the package scopes its own data by realm, and the one piece of isolation your application has to provide.
---

The package can serve several **realms** — isolated sets of clients, tokens, sessions and signing
keys — from one deployment. It stores a realm identifier on its own rows and scopes every lookup
by it.

It does **not** define what a realm is. There is no realm table here, no foreign key, and no
administration. A realm is an opaque string; its name, branding and administrators belong to your
application, exactly as the user model does.

## Resolving the realm

Everything hangs off one contract:

```php
interface RealmResolver
{
    public function current(): string;
}
```

The default `ConfiguredRealmResolver` returns `config('oidc.realm')` (`'default'` when unset), so a
single-realm install behaves as it always did. Bind your own to derive it per request:

```php
use Bambamboole\LaravelOidc\Server\Contracts\RealmResolver;

$this->app->scoped(RealmResolver::class, fn (): RealmResolver => new class implements RealmResolver
{
    public function current(): string
    {
        return Str::before(request()->getHost(), '.');
    }
});
```

Bind it **scoped**, not as a singleton — the realm is per request. Anything longer-lived than a
request must call `current()` per use rather than hold the resolver; the signing key store does
exactly that.

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

Write a test that signs in with realm A's credentials against realm B's host and asserts a
failure. It is the one bug in this area that stays silent in production.
:::

## Sessions

If you resolve realms from a **host** (`acme.idp.example.com`), the browser isolates session
cookies for you and a stashed authorize request cannot leak between realms.

If you resolve them from a **path** (`/realms/acme/...`), every realm shares one session cookie.
Scope `session.path` per realm, or a login in one realm is a login in all of them.
