---
title: Frontend setup
description: Publish the passkey stub components, register them in your Lattice registry, and wire up the sprite icon and i18n strings they need.
---

Two of this package's pages — passkey sign-in on the login page and passkey registration on a
settings page — render through frontend components the host app must register itself. The
package ships them as publishable stubs rather than bundling them, so the app controls its own
build.

## Publish the stubs

```bash
php artisan vendor:publish --tag=oidc-ui-js
```

This copies `passkey-verify.tsx` and `passkey-registration.tsx` into
`resources/js/vendor/oidc-ui/`.

## Register them in your Lattice registry

Add both to your app's Lattice component registry (`resources/js/registry.ts`), under the
component types `oidc.passkey-verify` and `oidc.passkey-registration`:

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

## Sprite icon (`brand_icon`)

`AuthLayout` renders an `Icon` sized from `config('oidc-ui.brand_icon')` (default `logo`) at the
top of every auth-flow page. It resolves against your app's own Lattice SVG sprite, so that
sprite must define a symbol with a matching name — there is no bundled icon. See
[Overriding views](/ui/overriding/) for changing the icon name itself.

## Passkey UI strings

The passkey components pull their copy from the host app's `app` i18n namespace via
`useT("app")`, keyed under `passkey.*` (e.g. `passkey.sign-in`, `passkey.register`,
`passkey.authenticating`). Every key has an English fallback baked into the component, so
nothing is required to get a working UI — add `passkey.*` entries to your own `app`
translation files only to customize or localize the copy.
