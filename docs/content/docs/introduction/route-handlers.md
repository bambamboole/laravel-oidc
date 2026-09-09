---
title: Routes
description: How the package registers its HTTP endpoints and how to replace a controller.
---

The package registers its endpoints from a plain routes file, loaded by `OidcServiceProvider`.
Every route sits below the realm prefix and carries a stable name:

```
/realms/{realm}/oauth/authorize        oidc.authorize
/realms/{realm}/oauth/token            oidc.token
/realms/{realm}/auth/login             identity.login
```

Paths and HTTP verbs are intrinsic to the package. Route **names** are the stable contract — the
UI package, the client package and your own application all generate URLs through them, never
through literal paths.

## Replacing a controller

Controllers are referenced by class name, so Laravel resolves them through the container. Bind
your own implementation to swap one out:

```php
use Bambamboole\LaravelOidc\Server\Protocol\Controllers\UserinfoController;

public function register(): void
{
    $this->app->bind(UserinfoController::class, MyUserinfoController::class);
}
```

The route, its name and its middleware stay as they are; only the class handling the request
changes.

## Middleware

`oidc.routes.middleware` is prepended to every package route. Use it for concerns that apply to
the whole provider surface — a maintenance gate, request logging, a tenancy initializer.

Per-endpoint middleware is intrinsic: the auth-engine routes carry `web` plus the appropriate
`guest`/`AuthenticateIdentity` middleware for the configured guard, and every endpoint that
accepts, mints or mails a credential carries a throttle.

## Realm parameter

The realm is a route parameter, but [ResolveRealm](/docs/provider/realms) removes it from the
matched route before the controller runs, so controller signatures do not carry it. URL
generation fills it in from the current request, which means `route('oidc.authorize')` keeps
working unchanged.

## What the routes file covers

- **Protocol** — authorize, token, approve/deny, userinfo, logout, introspect, revoke, discovery,
  JWKS, authorization server metadata, protected resource metadata, dynamic client registration.
- **Auth engine** — login, register, forgot/reset password, password confirmation, email
  verification, two-factor challenge and management, passkey registration/login/confirmation,
  social redirect/callback/linking.

Dynamic client registration is the one endpoint whose registration is conditional: it is bound
only when `oidc.clients.registration.enabled` is true, and the discovery document advertises
`registration_endpoint` only when the route exists.
