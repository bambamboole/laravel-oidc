---
title: Overriding views
description: Re-bind a single auth view contract, override the auth layout, and the logout_route/brand_icon config contracts.
---

Every screen this package renders goes through the view contracts the server package defines —
you override any one of them without forking the package.

## Overriding a single view

Package service providers complete their `register()` phase — this package's included — before
any provider's `boot()` runs, so a bind performed in your own provider's `register()` or `boot()`
simply wins. Rebind exactly the contract you want to change; every other contract keeps the
package's default:

```php
use App\Auth\Pages\CustomLoginPage;
use Bambamboole\LaravelOidc\Auth\Views\LoginView;

$this->app->bind(LoginView::class, CustomLoginPage::class);
```

`CustomLoginPage` must implement `LoginView` and be constructible with no required arguments —
the controller resolves it with `app(LoginView::class)->respond($prompt, $request)`, so it
receives the real `LoginPrompt` only inside `respond()` (see [View seams](/auth/overview/) for
the full contract list and the zero-arg-constructible requirement).

The OAuth consent page is not a special case — it is bound the same way, through `ConsentView`
(see [Endpoints & discovery](/provider/endpoints/#consent-view-required)); rebind it identically,
in your own provider.

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
