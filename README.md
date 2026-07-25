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

One-time setup until the split packages are on Packagist — resolve the server
dep from the monorepo:

```bash
composer --working-dir=packages/ui config repositories.server '{"type":"path","url":"../server","options":{"symlink":true,"versions":{"bambamboole/laravel-oidc-server":"0.7.0"}}}'
```

This edits `packages/ui/composer.json` locally; do not commit that change.

```bash
composer install:all   # composer install in every package
composer check         # pint --test + phpstan + pest, per package
```

Docs: https://bambamboole.github.io/laravel-oidc (built from `docs/`).
