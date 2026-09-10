# laravel-oidc-ui

A [Lattice](https://lattice-php.dev)-powered authentication UI for
[`bambamboole/laravel-oidc`](https://github.com/bambamboole/laravel-oidc) (the OIDC
provider) — login, passkeys, TOTP, and recovery-code screens rendered as Lattice
pages/components instead of Blade views, ready to drop into a Passkeys-backed
app.

📖 **[Read the documentation →](https://bambamboole.github.io/laravel-oidc)**

## Installation

```bash
composer require bambamboole/laravel-oidc-ui

# Optional: publish the config or translations to customize them
php artisan vendor:publish --tag=oidc-ui-config
php artisan vendor:publish --tag=oidc-ui-lang
```

The service provider is auto-discovered and merges the `oidc-ui` config and
`oidc-ui::` translation namespace automatically.

## Frontend

Nothing to register. This package declares `extra.lattice.plugin` in its
`composer.json`, so the app's `lattice()` Vite plugin picks up its component
plugin — `oidc.passkey-verify` and `oidc.passkey-registration` — automatically
through `virtual:lattice/plugins`, the same mechanism that discovers every
other installed Lattice component package.

Every screen this package renders is bound to its server-defined view contract (e.g.
`LoginView`, `ConsentView`) in the container — rebind any one of them in your own
service provider to override a single view without forking the package.

The passkey components' strings live in the `oidc-ui` i18n namespace (English
fallbacks are built into the components, so translations are optional) —
publish `oidc-ui-lang` and edit `lang/vendor/oidc-ui/{locale}/passkey.php` to
override them.

The verify-email page shows a log-out link only if the host app defines a logout
route (name configurable via `oidc-ui.logout_route`, default `logout`); apps built on
`bambamboole/laravel-oidc-client` already get a `logout` route from that package.

## Development

The suite runs from the root of the
[monorepo](https://github.com/bambamboole/laravel-oidc), which holds the single Composer
install for all packages (the root autoloads `packages/server` directly, so no path
repository is needed):

```bash
composer install
composer check   # pint --test, phpstan, rector --dry-run, pest
```

## Changelog

All packages in the suite are versioned in lockstep; see the
[monorepo changelog](https://github.com/bambamboole/laravel-oidc/blob/main/CHANGELOG.md).

## License

MIT.
