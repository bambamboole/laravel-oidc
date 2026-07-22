# Local development

`bambamboole/laravel-oidc-server` is not published on Packagist yet. To install
this package's dependencies locally (from a checkout of the monorepo), point
Composer at the sibling `packages/server` checkout with a pinned version
before running `composer install`:

```bash
cd packages/ui
composer config repositories.server '{"type":"path","url":"../server","options":{"symlink":true,"versions":{"bambamboole/laravel-oidc-server":"0.7.0"}}}'
composer install
```

This writes a `repositories.server` entry into `composer.json` — do not commit
it. Revert it once `composer.lock` has been generated (`composer.lock` is
git-ignored, so `vendor/` and the lock file are unaffected by the revert):

```bash
git checkout -- composer.json
```

CI re-runs the same `composer config` command before installing (see the
workflow that builds this package).
