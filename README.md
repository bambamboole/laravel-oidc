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

The repo root is the single Composer project for all Laravel packages: it
autoloads `packages/*/src` directly and runs the tooling through Orchestra
Testbench (`php artisan …` boots the Testbench skeleton with the `workbench/`
app).

```bash
composer install       # also activates the git hooks in .githooks/
composer check         # pint --test + phpstan + rector --dry-run + pest --parallel
composer test          # pest only
composer fix           # rector + pint
composer boost:refresh # regenerate CLAUDE.md / AGENTS.md from .ai/ and boost.json
```

`packages/mautic` is a Symfony bundle with its own install:
`composer install:mautic` and `composer check:mautic`.

Docs: https://bambamboole.github.io/laravel-oidc (built from `docs/`).
