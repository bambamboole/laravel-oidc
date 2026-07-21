---
title: Overriding views
description: Re-bind a single AuthViewManager view, override the auth layout, and the logout_route/brand_icon config contracts.
---

Every screen this package renders goes through the seams the server package defines — you
override any one of them without forking the package.

## Overriding a single view

Package service providers boot before application providers, so a bind performed in your own
provider's `boot()` simply wins — later bind takes the seam. Rebind exactly the
`AuthViewManager` entry you want to change; every other entry keeps the package's default:

```php
use Bambamboole\LaravelOidc\Auth\AuthViewManager;

$this->app->make(AuthViewManager::class)->bind(
    AuthViewManager::Login,
    fn ($request) => new App\Auth\Pages\CustomLoginPage,
);
```

The OAuth consent page is not on `AuthViewManager` — it goes through
`Passport::authorizationView()` directly (see
[Endpoints & discovery](/provider/endpoints/)); rebind it the same way, later in your own
provider's `boot()`.

## Overriding the `auth` layout

`AuthLayout` is registered with Lattice's `LayoutRegistry` explicitly, not discovered from the
filesystem — Lattice's discovery only scans `config('lattice.discover')` paths (the host app's
`app/` directory by default), so it would never see this package's `src/Layouts` directory.

Because the package's `register()` call already wins over filesystem discovery, replacing the
`auth` layout needs the same explicit call in your own app's `boot()`, registered after the
package's provider runs:

```php
use Lattice\Lattice\Layouts\LayoutRegistry;

$this->app->make(LayoutRegistry::class)->register(App\Auth\Layouts\CustomAuthLayout::class);
```

A layout registered under the same name (`auth`) replaces the package's.

## `oidc-ui.logout_route`

The verify-email page shows a log-out link only when the route named by
`config('oidc-ui.logout_route')` (default `logout`) is registered — the link is silently
omitted otherwise. Apps built on `bambamboole/laravel-oidc-client` already get a `logout` route
from that package. Point the config at a different route name if yours differs:

```php
// config/oidc-ui.php
'logout_route' => 'my-app.logout',
```

## `oidc-ui.brand_icon`

`AuthLayout` renders `config('oidc-ui.brand_icon')` (default `logo`) as a Lattice sprite icon
name — your app's SVG sprite must define a symbol with that name. Change it to point at your
own brand mark:

```php
// config/oidc-ui.php
'brand_icon' => 'my-app-mark',
```
