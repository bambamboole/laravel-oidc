# laravel-oidc

OpenID Connect suite for Laravel, developed as a monorepo.

| Package | Path | Install standalone as |
| --- | --- | --- |
| Master (everything below) | repo root | `bambamboole/laravel-oidc` |
| Identity provider (server) | `packages/server` | `bambamboole/laravel-oidc-server` |
| Relying party (client) | `packages/client` | `bambamboole/laravel-oidc-client` |
| Auth UI (ui) | `packages/ui` | `bambamboole/laravel-oidc-ui` |

`bambamboole/laravel-oidc` ships the whole suite and `replace`s the split
packages. Install the split packages individually if you only need one side
of the protocol.

## Development

Each package is a self-contained Composer project. From the repo root:

```bash
composer install:all   # composer install in every package
composer check         # pint --test + phpstan + pest, per package
```

`bambamboole/laravel-oidc-server` is not on Packagist yet, so the ui install
resolves it from the sibling `packages/server` checkout: `install:all` backs up
`packages/ui/composer.json`, writes a path repository into it (version taken
from `.release-please-manifest.json`), runs the install, and restores the file —
`composer.json` ends up unchanged, and `composer.lock` is git-ignored. See
`packages/ui/composer.local-dev.md` for the manual equivalent.

Docs: https://bambamboole.github.io/laravel-oidc (built from `docs/`).
