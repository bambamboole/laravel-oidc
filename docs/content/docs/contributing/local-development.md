---
title: Local development
description: Setting up the package, running the test/lint/analysis gates, the testbench harness, and the docs site.
---

## Getting started

```bash
git clone git@github.com:bambamboole/laravel-oidc.git
cd laravel-oidc
composer install
```

The package is developed against a [Orchestra Testbench](https://packages.tools/testbench)
workbench harness — there is no full Laravel app to boot. `composer install` runs
`testbench package:discover` (via the `post-autoload-dump` script) to wire the package into the
harness; `composer clear` purges the generated skeleton if you need to reset it.

## The quality gates

Three composer scripts back the CI matrix (Laravel 12 / 13). Run them individually, or run all
three at once with `composer check`:

```bash
composer test        # Pest test suite
composer test:lint   # Pint in --test mode (fails on style violations)
composer analyse     # PHPStan static analysis
composer check       # runs test:lint, analyse, and test in sequence
```

To auto-fix code style instead of just checking it:

```bash
composer lint        # Pint (applies fixes)
```

Run `composer check` before opening a pull request — it mirrors what CI enforces.

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
