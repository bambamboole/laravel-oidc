# laravel-oidc-ui

A [Lattice](https://lattice-php.dev)-powered authentication UI for
[`bambamboole/laravel-oidc`](https://github.com/bambamboole/laravel-oidc) (the OIDC
provider) — login, passkeys, TOTP, and recovery-code screens rendered as Lattice
pages/components instead of Blade views, ready to drop into a Passport/Passkeys-backed
app.

## Installation

```bash
composer require bambamboole/laravel-oidc-ui

# Optional: publish the config, translations, and frontend stub components
php artisan vendor:publish --tag=oidc-ui-config
php artisan vendor:publish --tag=oidc-ui-lang
php artisan vendor:publish --tag=oidc-ui-js
```

The service provider is auto-discovered and merges the `oidc-ui` config and
`oidc-ui::` translation namespace automatically.

## Frontend registration

The `oidc-ui-js` publish tag copies stub components into
`resources/js/vendor/oidc-ui`. Register them in your app's Lattice component
registry (`resources/js/registry.ts`) alongside your own:

```ts
import {
    createPlugin,
    extendRegistry,
    eagerComponent,
    registry as packageRegistry,
} from "@lattice-php/lattice";
import PasskeyVerify from "./vendor/oidc-ui/passkey-verify";
import PasskeyRegistration from "./vendor/oidc-ui/passkey-registration";

export const registry = extendRegistry(
    packageRegistry,
    createPlugin({
        components: {
            "oidc.passkey-verify": eagerComponent(PasskeyVerify),
            "oidc.passkey-registration": eagerComponent(PasskeyRegistration),
        },
        name: "oidc-ui",
    }),
);
```

Every screen this package renders is resolved through `AuthViewManager` — rebind any
entry on that manager in your own service provider to override a single view without
forking the package.

## Development

`bambamboole/laravel-oidc-server` is not published on Packagist yet, so local installs
need a path repository pointed at the sibling checkout — see
[`composer.local-dev.md`](composer.local-dev.md):

```bash
composer config repositories.server '{"type":"path","url":"../server","options":{"symlink":true,"versions":{"bambamboole/laravel-oidc-server":"0.6.0"}}}'
composer install
composer check   # pint --test, phpstan, pest
git checkout -- composer.json   # drop the local-only repositories entry before committing
```

## License

MIT.
