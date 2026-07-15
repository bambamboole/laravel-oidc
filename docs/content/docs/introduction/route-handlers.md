---
title: Route handlers
description: How the package registers, customizes, and disables every HTTP endpoint.
---

Every endpoint the package registers lives in `config('oidc.handlers')`, a flat map keyed by the
`Bambamboole\LaravelOidc\Routing\Handler` enum. Each entry has three keys and is registered by a
single `HandlerRegistrar`:

```php
use Bambamboole\LaravelOidc\Routing\Handler;

Handler::Userinfo->value => [
    'route' => 'oauth/userinfo',                 // URI path (literal)
    'controller' => UserinfoController::class,   // invokable class, or [Class::class, 'method']
    'middleware' => [],
],
```

## Customizing an endpoint

Customize any entry — point it at your own controller, change its path, or adjust its
middleware — or set it to `false` to disable that endpoint entirely. The HTTP verb is intrinsic
to each endpoint (defined on `Handler::method()`) and is therefore not configurable.

Because paths are literal, the `/oauth/*` routes do not automatically follow
`config('passport.path')`; if you change Passport's prefix, update the corresponding handler
paths (and the `guest`/`auth` guard middleware if you run a non-default guard).

## Disabling an endpoint

Set a handler to `false` to remove its route. The protocol endpoints most commonly toggled off
are `Handler::Userinfo`, `Handler::Logout`, `Handler::Introspect`, and `Handler::Revoke`.

## Resolving a handler's config

Resolve a handler's configuration anywhere via the facade instead of reading config directly —
it returns a `HandlerConfig` DTO, or `false` when the handler is disabled:

```php
use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Routing\Handler;

$config = Oidc::handlerConfig(Handler::Userinfo); // HandlerConfig|false
$issuer = Oidc::issuer();                          // issuer URL
```

## What lives in the handler map

The map covers three groups of endpoints:

- **Protocol** — authorize, token, token refresh, approve/deny, userinfo, logout, introspect,
  revoke, discovery, JWKS.
- **Auth engine** — login, register, forgot/reset password, password confirmation, email
  verification, two-factor challenge and management, passkey registration/login/confirmation.
- Each auth-engine route is named `identity.*` (e.g. `identity.login`) and carries the
  appropriate `web` + `guest`/`AuthenticateIdentity` middleware for its guard.
