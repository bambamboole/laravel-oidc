# Local development

`bambamboole/laravel-oidc-server` is not published on Packagist yet. Composer
resolves it from the sibling `packages/server` checkout via a temporary path
repository with a pinned version taken from `.release-please-manifest.json`.

From the monorepo root, `composer install:all` (or `composer install:ui` for
this package alone) handles the whole dance: it backs up `composer.json`,
writes the `repositories.server` entry, runs `composer install`, and restores
`composer.json` — so the repositories entry never ends up committed
(`composer.lock` is git-ignored and keeps referencing the path repository).

The manual equivalent, from `packages/ui`:

```bash
VERSION=$(php -r 'echo json_decode(file_get_contents("../../.release-please-manifest.json"), true)["."];')
composer config repositories.server "{\"type\":\"path\",\"url\":\"../server\",\"options\":{\"symlink\":true,\"versions\":{\"bambamboole/laravel-oidc-server\":\"$VERSION\"}}}"
composer install
git checkout -- composer.json   # drop the local-only repositories entry
```

CI re-runs the same `composer config` command before installing (see the
workflow that builds this package).
