---
title: Local development
description: Setting up the monorepo, running the test/lint/analysis gates per package, and working on the docs site.
---

## Repository layout

The repository is a monorepo holding three packages:

| Path | Package | Contents |
| --- | --- | --- |
| `packages/server` | `bambamboole/laravel-oidc-server` | The OIDC provider and auth engine |
| `packages/client` | `bambamboole/laravel-oidc-client` | The relying-party client |
| `packages/ui` | `bambamboole/laravel-oidc-ui` | The Lattice-powered auth UI |

Each package has its own `composer.json`, test suite, and tooling; the root `composer.json`
provides aggregate scripts that fan out across all three.

## Getting started

```bash
git clone git@github.com:bambamboole/laravel-oidc.git
cd laravel-oidc
composer install:all
```

`composer install:all` runs `composer install` inside each package. The packages are developed
against an [Orchestra Testbench](https://packages.tools/testbench) harness — there is no full
Laravel app to boot. Each package's install wires itself into its harness via its
`post-autoload-dump` script (`testbench package:discover`); `composer clear` inside a package
purges the generated skeleton if you need to reset it.

## The quality gates

From the repository root:

```bash
composer check          # every package: Pint --test, PHPStan, Pest in sequence
composer check:server   # a single package's gate
composer check:client
composer check:ui
composer test           # every package's Pest suite only
```

Inside a package directory, the individual tools are available directly:

```bash
composer test:lint   # Pint in --test mode (fails on style violations)
composer analyse     # PHPStan static analysis
composer test        # Pest test suite
composer lint        # Pint (applies fixes)
```

Run `composer check` from the root before opening a pull request — CI runs the same tools
across a Laravel 12 / 13 matrix.

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
