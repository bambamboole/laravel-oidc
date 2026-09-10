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

Everything hangs off two contracts in `Bambamboole\LaravelOidc\Server\Realms`:

```php
interface RealmResolver
{
    public function current(): Realm;
}

interface RealmRepository
{
    public function find(string $id): ?Realm;
}
```

The default `RouteRealmResolver` reads the `{realm}` route parameter, asks the bound
`RealmRepository` for it, and answers 404 for an identifier the repository does not know. Outside
a matched route — console commands, queued jobs — it falls back to `config('oidc.realm')`. The
default `ConfiguredRealmRepository` accepts every identifier and serves it with the configured
settings, so a deployment that only scopes data per tenant needs no code.

An application with a realm model binds the repository:

```php
use Bambamboole\LaravelOidc\Server\Shared\Realms\RealmRepository;

$this->app->singleton(RealmRepository::class, EloquentRealmRepository::class);
```

Both contracts are singletons. A resolver must derive the realm from the current request on
every call rather than remember it — under Octane one instance serves many requests.

## Realm settings

`Realm` is the contract your model implements. Beyond its identifier it exposes eight typed
settings objects; every behavior that may differ between tenants reads from them instead of
from `config('oidc.*')`:

| Method | Settings object | Drives |
| --- | --- | --- |
| `tokens()` | `TokenSettings` | access, id, client-credentials and refresh token lifetimes; the realm's audiences |
| `sessions()` | `SessionSettings` | SSO session absolute lifetime; session root token TTL, refresh skew and scopes |
| `login()` | `LoginSettings` | username field, home URL, login route, logout redirect, `acr` values |
| `credentials()` | `CredentialSettings` | challengeable factor providers, TOTP secret length and window, recovery code count |
| `brokering()` | `BrokeringSettings` | upstream identity providers, link-by-verified-email, auto-provisioning |
| `scopes()` | `ScopeSettings` | the scope catalog and the advertised `claims_supported` |
| `clients()` | `ClientSettings` | dynamic registration and its redirect rules, token exchange, the first-party and trusted clients |
| `keys()` | `KeySettings` | RSA key size for generated signing keys |

The settings objects live in `Bambamboole\LaravelOidc\Server\Realms\Settings`; each is a
`final readonly` value object with a `fromConfig()` constructor. `ConfiguredRealm` implements
the whole contract from `config('oidc.*')`, so a model can delegate what it does not store:

```php
use Bambamboole\LaravelOidc\Server\Realms\ConfiguredRealm;
use Bambamboole\LaravelOidc\Server\Shared\Realms\Realm;
use Bambamboole\LaravelOidc\Server\Shared\Realms\Settings\TokenSettings;

class Realm extends Model implements Realm
{
    public function id(): string
    {
        return $this->slug;
    }

    public function tokens(): TokenSettings
    {
        return new TokenSettings(
            accessTokenLifetime: $this->access_token_lifetime,
            refreshTokenLifetime: $this->refresh_token_lifetime,
        );
    }

    // sessions(), login(), … delegate to (new ConfiguredRealm($this->slug))->sessions() etc.
}
```

What stays in `config/oidc.php` is deployment-wide by nature: the issuer origin, guard and
provider names, the signing key store and key material, the audit sink, route middleware,
the advertised protected resources, and the install-time first-party provisioning values.

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

`realm_id` is stored on `oidc_clients`, `oidc_access_tokens`, `oidc_refresh_tokens`,
`oidc_auth_codes`, `oidc_consents`, `oidc_sessions`, `oidc_signing_keys`,
`oidc_authentication_contexts` and `oidc_social_accounts`. A `client_id` only has to be unique
**within** its realm, so the same readable name can exist in several; likewise a social
provider's user id is unique per realm, so the same upstream identity can be linked to a different
user in each realm. Session participants carry no realm of their own — they belong to a session,
which does.

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
