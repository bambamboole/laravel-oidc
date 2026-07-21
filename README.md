# laravel-oidc

OpenID Connect suite for Laravel, developed as a monorepo.

| Package | Path | Install standalone as |
| --- | --- | --- |
| Master (everything below) | repo root | `bambamboole/laravel-oidc` |
| Identity provider (server) | `packages/server` | `bambamboole/laravel-oidc-server` |
| Relying party (client) | `packages/client` | `bambamboole/laravel-oidc-client` |

`bambamboole/laravel-oidc` ships the whole suite and `replace`s the split
packages. Install the split packages individually if you only need one side
of the protocol.

## Development

Each package is a self-contained Composer project. From the repo root:

```bash
composer install:all   # composer install in every package
composer check         # pint --test + phpstan + pest, per package
```

Docs: https://bambamboole.github.io/laravel-oidc (built from `docs/`).
