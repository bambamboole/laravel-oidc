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

Where realms appear in URLs is `oidc.routes.realms`:

- **`single`** (the default) serves the realm named by `oidc.realm` from the application root.
  There is no realm segment, and the issuer is the bare origin — `oidc.issuer` or `app.url`:

  ```
  https://id.example.com/.well-known/openid-configuration
  https://id.example.com/oauth/authorize
  https://id.example.com/auth/login

  issuer = https://id.example.com
  ```

- **`path`** serves every realm below `/realms/{realm}`. Each realm is its own OpenID Provider
  with its own issuer; the origin comes from `oidc.issuer` and the realm supplies the path:

  ```
  https://id.example.com/realms/acme/.well-known/openid-configuration
  https://id.example.com/realms/acme/oauth/authorize
  https://id.example.com/realms/acme/auth/login

  issuer = https://id.example.com/realms/acme
  ```

  A relying party configured against `https://id.example.com/realms/acme` discovers, verifies and
  logs in entirely within one realm. More than one realm per deployment requires this or `domain`.

- **`domain`** serves every realm from its own host. Each realm is its own origin, so every
  endpoint keeps the path it has in `single` and the issuer is the host itself:

  ```
  https://acme.id.example.com/.well-known/openid-configuration
  https://acme.id.example.com/oauth/authorize
  https://acme.id.example.com/auth/login

  issuer = https://acme.id.example.com
  ```

  Map each host to a realm in `oidc.routes.domains`:

  ```php
  'routes' => [
      'realms' => 'domain',
      'domains' => [
          'acme.id.example.com' => 'acme',
          'login.globex.example' => 'globex',
      ],
  ],
  ```

  An application with a realm model skips the map: it resolves the host in its own
  [`RealmRepository`](#resolving-the-realm) and names it in `Realm::host()`, which is what lets a
  customer bring their own domain. A host no realm is served from answers 404.

  The issuer is the realm's host with the scheme and port of `oidc.issuer`, whichever host the
  request, queued job or console command runs on. Every absolute URL to a route behind
  `ResolveRealm` — the package's own and any of yours — points at the current realm's host, so a
  password reset sent from an admin console on another host links into the realm it belongs to.

  :::danger[The Host header chooses the realm]
  Configure Laravel's trusted hosts (and trusted proxies behind a load balancer) before using this
  mode. An unvalidated `Host` header would otherwise let a caller pick the realm, and with it the
  issuer the tokens claim.
  :::

  Because each realm is a separate origin, the browser isolates its cookies, so this mode needs no
  per-realm session cookie the way `path` does.

The mode is fixed when the routes are registered; the route **names** are the same in all three.

:::caution[`oidc.issuer` is an origin, not a base URL]
It must have no path of its own. Routes are registered at the application root, and in `path` mode
the realm supplies the only path there is, so `https://example.com/idp` would be carried into the
discovery document while the endpoints stayed at `https://example.com/oauth/…` — an issuer whose
own metadata URL answers 404. Serve the provider from its own host or subdomain, or use `domain`
mode, where each realm's host is the origin and `oidc.issuer` supplies only the scheme and port.
:::

### Metadata that is not prefixed

RFC 8414 and RFC 9728 build their metadata URLs by inserting the well-known segment *ahead* of
the issuer's path rather than appending it. In `single` and `domain` mode the issuer has no path,
so both documents sit directly below `/.well-known/`. In `path` mode the realm follows the
well-known segment:

```
https://id.example.com/.well-known/oauth-authorization-server/realms/acme
https://id.example.com/.well-known/oauth-protected-resource/realms/acme/mcp
```

OpenID Connect Discovery appends instead, which is why
`/realms/acme/.well-known/openid-configuration` is the prefixed one. Both serve the same
document.

## Resolving the realm

Everything hangs off two contracts in `Bambamboole\LaravelOidc\Server\Shared\Realms`:

```php
interface RealmResolver
{
    public function current(): Realm;
}

interface RealmRepository
{
    public function find(string $id): ?Realm;

    public function findByDomain(string $host): ?Realm;
}
```

In `single` and `path` mode the `RouteRealmResolver` reads the `{realm}` route parameter, asks the
bound `RealmRepository` for it, and answers 404 for an identifier the repository does not know. In
`domain` mode the `DomainRealmResolver` asks `findByDomain()` for the request host instead, and a
host no realm is served from is a 404 just the same. Without a realm to read — every request in
`single` mode, and console commands or queued jobs in any mode — resolution falls back to the
[context](#realms-outside-a-request) and then to `config('oidc.realm')`. The
default `ConfiguredRealmRepository` accepts every identifier, serves it with the configured
settings, and maps hosts through `oidc.routes.domains`, so a deployment that only scopes data per
tenant needs no code.

An application with a realm model binds the repository:

```php
use Bambamboole\LaravelOidc\Server\Shared\Realms\RealmRepository;

$this->app->singleton(RealmRepository::class, EloquentRealmRepository::class);
```

Both contracts are singletons. A resolver must derive the realm from the current request on
every call rather than remember it — under Octane one instance serves many requests. Resolution
therefore runs many times per request, and in `domain` mode so does `findByDomain()`. Memoize inside the repository, keyed by the value looked up, rather than caching a realm on the
resolver.

The shipped resolvers read the realm from the URL — the `{realm}` path segment, or the single
configured realm. The cacheable documents (`jwks.json`, discovery, RFC 8414 and RFC 9728 metadata)
therefore carry `Cache-Control: public` safely, because a shared cache keys on the URI. A resolver
that derives the realm from anything outside the URL, a request header say, must add a matching
`Vary` to those responses, or a proxy will serve one realm's keys to another.

## Realm settings

`Realm` is the contract your model implements. Beyond its identifier and, in `domain` routing,
its `host()`, it exposes ten typed
settings objects; every behavior that may differ between tenants reads from them instead of
from `config('oidc.*')`:

| Method | Settings object | Drives |
| --- | --- | --- |
| `tokens()` | `TokenSettings` | access, id, client-credentials and refresh token lifetimes |
| `resources()` | `ResourceSettings` | the resource servers the realm serves besides itself: audiences a client may request and RFC 9728 metadata |
| `sessions()` | `SessionSettings` | SSO session absolute lifetime; session root token TTL, refresh skew and scopes; the provider session cookie name in `path` mode |
| `login()` | `LoginSettings` | username field, home URL, login route, logout redirect, `acr` values |
| `authentication()` | `AuthenticationSettings` | the login methods the realm accepts, how hard it insists on a second factor, whether an unverified email address blocks the login |
| `credentials()` | `CredentialSettings` | challengeable factor providers, TOTP secret length and window, recovery code count, the password policy |
| `brokering()` | `BrokeringSettings` | upstream identity providers, link-by-verified-email, auto-provisioning |
| `scopes()` | `ScopeSettings` | the scope catalog and the advertised `claims_supported` |
| `clients()` | `ClientSettings` | dynamic registration and its redirect rules, the default and optional scopes new clients are assigned, token exchange, the first-party and trusted clients |
| `keys()` | `KeySettings` | RSA key size for generated signing keys |

The settings objects live in `Bambamboole\LaravelOidc\Server\Shared\Realms\Settings`; each is a
`final readonly` value object with a `fromConfig()` constructor. `ConfiguredRealm` implements
the whole contract from `config('oidc.*')`, so a model can delegate what it does not store:

```php
use Bambamboole\LaravelOidc\Server\Realms\ConfiguredRealm;
use Bambamboole\LaravelOidc\Server\Shared\Realms\Realm;
use Bambamboole\LaravelOidc\Server\Shared\Realms\Settings\TokenSettings;

class Tenant extends Model implements Realm
{
    public function identifier(): string
    {
        return $this->slug;
    }

    public function host(): ?string
    {
        return $this->domain;
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

The `ResolveRealm` middleware runs on every package route: it records the realm on the request,
registers it as the default `{realm}` for URL generation, publishes it to the context, and — in
`path` mode — gives the provider its own session cookie (see [Sessions](#sessions)).

It also removes `{realm}` from the matched route's parameters. That is load-bearing rather than
cosmetic: Laravel hands route parameters to controller methods positionally, so a leading realm
would shift every other argument along by one.

### Realms outside a request

There is no request to read a realm from in a queued job, so the realm travels with the job
instead. `ResolveRealm` publishes the resolved realm to
[Laravel's context](https://laravel.com/docs/context), which Laravel serializes into every job
dispatched while serving the request and restores before the job runs:

```php
Context::get('oidc.realm'); // 'acme', in the request and in every job it queued
```

The resolver reads it back, so a job dispatched from `acme` resolves `acme` on the worker: its
`inRealm()` queries hit that realm's rows, `IssuerResolver` names that realm's issuer, and the
`Keyring` signs with that realm's key. `route('oidc.authorize')` gets the same treatment — the
package syncs the default `{realm}` from the context before each job runs, so queued
password-reset and email-verification notifications link back into the realm that sent them.

The constants are on `Bambamboole\LaravelOidc\Server\Shared\Context\OidcContext`, which also
publishes `oidc.client_id` — the client of the authorize or token request — for the same reason
and for log correlation.

A job dispatched from outside a package route (a console command, or one of your own controllers
in a multi-realm deployment) carries no realm and falls back to `config('oidc.realm')`. Set it
yourself when that is not the realm you mean:

```php
Context::add('oidc.realm', $realm);
```

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

In `single` mode the provider shares the application's session: the same cookie, the same
session id. A self-SSO deployment — the application is its own relying party on the same host —
relies on that, because Laravel's CSRF cookie has one fixed name and two independent sessions on
one path would overwrite each other's token.

In `path` mode each realm gets its own session cookie, scoped to `/realms/{realm}`, so a browser
session in one realm is not sent to another and a login in the provider cannot consume or
regenerate the relying party's session. The cookie is named `{session.cookie}-oidc-{realm}` (dots
in the realm id become underscores) unless the realm's `SessionSettings::$cookieName` — from
`oidc.session.cookie_name` by default — names it; the name must differ from the application's
and consist of letters, digits, underscores or hyphens. When a provider response redirects out of
the realm, the provider's CSRF cookie is expired so the application's own is used again.

In both modes the realm's SSO session (`oidc_sessions`) and its stashed authorize request are
scoped by `realm_id`.
