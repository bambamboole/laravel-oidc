---
title: Local development
description: Setting up the monorepo, running the test/lint/analysis gates, and working on the docs site.
---

## Repository layout

The repository is a monorepo holding three packages:

| Path | Package | Contents |
| --- | --- | --- |
| `packages/server` | `bambamboole/laravel-oidc-server` | The OIDC provider and auth engine |
| `packages/client` | `bambamboole/laravel-oidc-client` | The relying-party client |
| `packages/ui` | `bambamboole/laravel-oidc-ui` | The Lattice-powered auth UI |

`packages/*/composer.json` are the split manifests consumers install. The repository root is the
single Composer project for development: it autoloads `packages/*/src` directly and runs the
tooling through an [Orchestra Testbench](https://packages.tools/testbench) harness — there is no
full Laravel app to boot. `artisan` at the root is a thin shim onto `vendor/bin/testbench`.

## Getting started

```bash
git clone git@github.com:bambamboole/laravel-oidc.git
cd laravel-oidc
composer install
```

`composer install` also points `core.hooksPath` at `.githooks/`, which runs Pint, Rector and
PHPStan on commit and Pint and PHPStan on push. `composer boost:refresh` regenerates the
Laravel Boost guidance (`CLAUDE.md`, `AGENTS.md`) from `.ai/` and `boost.json`.

`packages/mautic` is a Symfony bundle with its own install: `composer install:mautic` and
`composer check:mautic`.

## The quality gates

From the repository root:

```bash
composer check          # Pint --test, PHPStan (sources and tests), Rector --dry-run, Pest --parallel
composer test           # the Pest suites of all three packages
composer test:lint      # Pint in --test mode
composer analyse        # PHPStan, both configs
composer rector:test    # Rector in dry-run mode
composer fix            # Rector, then Pint
```

Tests live in `packages/*/tests` and run from the root; each package's `TestCase` registers only
its own providers, so a package suite proves the package works without the others.

Run `composer check` before opening a pull request — CI runs the same tools on Laravel 13.

## The docs site

The documentation is an [Astro Starlight](https://starlight.astro.build/) site — content lives
under `docs/`, and the Node toolchain runs from the repository root:

```bash
npm install
npm run docs:dev     # local dev server with hot reload
npm run docs:build   # production build
npm run docs:preview # preview the production build
```

Pages live under `docs/content/docs/` as Markdown with Starlight frontmatter (`title`,
`description`). Match the voice and cross-linking of the existing pages when adding new ones.
