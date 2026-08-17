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
use Bambamboole\LaravelOidc\Server\Auth\Views\LoginView;

$this->app->bind(LoginView::class, CustomLoginPage::class);
```

`CustomLoginPage` must implement `LoginView` and be resolvable by the container with no arguments
passed to `make()` — the controller resolves it with `app(LoginView::class)->respond($prompt,
$request)`, so it receives the real `LoginPrompt` only inside `respond()` (see [View
seams](/auth/overview/) for the full contract list and this requirement).

The OAuth consent page is not a special case — it is bound the same way, through `ConsentView`
(see [Endpoints & discovery](/provider/endpoints/#consent-view-required)); rebind it identically,
in your own provider.

## Extending a shipped page

Reimplementing a contract from scratch is the heavy option. When you only want to change part of
a page, extend the one this package ships and bind the subclass to the same contract — the page
that renders is your subclass, and `protected` members are the extension points:

```php
use Bambamboole\LaravelOidc\Ui\Pages\LoginPage;
use Lattice\Form\Components\TextInput;

final class DevLoginPage extends LoginPage
{
    #[\Override]
    protected function emailField(): TextInput
    {
        return parent::emailField()->value('test@example.com');
    }
}

// In your provider's register() or boot():
$this->app->bind(LoginView::class, DevLoginPage::class);
```

The prompt is available as `$this->prompt` (nullable — it is only set once `respond()` has run,
so `render()` is the earliest place it is safe to read). Page constructors are `final`: `respond()`
builds the rendering instance itself, so a subclass cannot take constructor arguments. Resolve
your own dependencies from the container inside `render()` instead.

## Overriding the `auth` layout

`AuthLayout` is discovered, not bound in `UiServiceProvider::register()` — this package's
`composer.json` declares `extra.lattice.discover: ["src"]`, so Lattice's root-manifest discovery
finds the `#[AsLayout('auth')]`-attributed class in `src/Layouts` on its own, the moment the
package is installed.

An explicit `LayoutRegistry::register()` call in your own app's provider still overrides it —
explicit registrations layer over the discovered manifest, regardless of which provider runs
first:

```php
use Lattice\Layouts\LayoutRegistry;

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
