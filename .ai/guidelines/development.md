# Local Development

- This repository is a monorepo: `packages/server`, `packages/client`, and `packages/ui` are the Laravel packages,
  `packages/mautic` is a Symfony bundle with its own Composer install (`composer install:mautic`, `composer check:mautic`).
- The repo root is the single Composer project for the Laravel packages. It autoloads `packages/*/src` directly, so
  there are no path repositories and no per-package `vendor/`. `packages/*/composer.json` are the split manifests
  consumers install; they carry no dev tooling.
- The packages are developed with Orchestra Testbench, not a full Laravel app. `artisan` at the repo root is a thin
  shim requiring `vendor/bin/testbench`, so `php artisan <command>` boots the Testbench skeleton with the providers from
  `testbench.yaml` and the `workbench/` app.
- `bambamboole/extended-testbench` rebases `base_path()` (and storage/config/database/bootstrap/lang/public paths) to
  the repo root for `boost:*`/`mcp:*` commands specifically, so Laravel Boost's package-guideline and skill discovery —
  which reads `base_path('composer.json')` — sees this monorepo instead of the Testbench skeleton.
- The Boost overrides live in `workbench/app/Support/` and are wired in
  `Workbench\App\Providers\WorkbenchServiceProvider`. They point `boost.json`, `.ai/guidelines/` and `.ai/skills/` at
  the repo root instead of the Testbench skeleton.
- Regenerate `CLAUDE.md` and `AGENTS.md` after editing files in `.ai/guidelines/` or `.ai/skills/` with
  `composer boost:refresh`.
- Tests live in `packages/*/tests` and run from the root (`composer test`, `composer test:parallel`). The root
  `tests/Pest.php` only requires the three package `Pest.php` files. Each package `TestCase` declares its own
  `getPackageProviders()` with package discovery disabled, so a package's suite proves it works without the other
  packages that share the root `vendor/`.
- The first consumer for the auth-engine work is `../saas-starter-kit`, but package behavior must be implemented and
  verified in this repository's Testbench harness first.

## Verification

- Git hooks enforce the gate automatically. `composer install` points `core.hooksPath` at `.githooks/`; if the hooks are
  not active, run `composer install` (or `git config core.hooksPath .githooks`) once.
  - **pre-commit** auto-fixes staged PHP with Pint and Rector, re-stages the fixes, then runs PHPStan (both configs)
    over the whole project and blocks on any error. PHPStan's result cache is pinned to `.phpstan-cache/` (gitignored,
    `parameters.tmpDir` in `phpstan.neon.dist`/`phpstan-tests.neon.dist`) so it persists across commits — after the
    first run, only files that actually changed get re-analysed.
  - **pre-push** runs Pint and PHPStan when the push touches PHP files. The full Pest suite and Rector are too slow
    for every push, so they run in CI and via explicit local runs (`composer check`).
- Before opening a PR, run the full local gate:
  ```bash
  composer check
  ```
- A consuming app may install `Date::use(CarbonImmutable::class)`, whose instances neither extend
  `Illuminate\Support\Carbon` nor mutate in place. `composer test:immutable` (`OIDC_TEST_DATES=immutable`, wired in
  `tests/Pest.php`) replays the whole suite under it and runs as part of `composer check` and its own CI matrix cell.
  Type package date values as `Carbon\CarbonInterface` or `DateTimeInterface`, never as the concrete
  `Illuminate\Support\Carbon`.
- For narrower loops while developing:
  ```bash
  composer test            # or composer test:parallel
  composer test:immutable  # the suite under Date::use(CarbonImmutable::class)
  composer test:lint
  composer analyse
  composer rector:test
  ```
- Never push on red. Use `git commit`/`git push --no-verify` only in emergencies.
- Do not add PHPStan suppressions or baselines unless the user explicitly approves them.
- Worktrees live as siblings of the checkout (see the `worktrees` skill). Nested worktrees inside the repo make the
  Pest PHPStan plugin pick up their `Pest.php` files and misresolve `$this` in the test suites.

## Comments

- Code must be self-explanatory: reach for clear names, small functions, and types before a comment.
- Do not add comments. A comment is a last resort and explains only *why* something is done, never *what* the code does.
- When you encounter an obsolete, redundant, or "what" comment, delete it.
- Delete section banners and navigation comments unless they explain a non-obvious boundary.
- Delete comments that narrate the next line, assertion, or obvious test setup; prefer clearer test names and variable
  names.
- Keep PHPDoc/JSDoc only when it carries type information, public API intent, static-analysis value, generated-file
  context, a Spec reference or a non-obvious constraint.
- Keep comments that explain framework quirks, ordering requirements, browser/test timing, cache/build behavior,
  performance traps, or other constraints that are hard to infer from the code alone.

## Testing

- Prefer feature tests for package behavior. Test through HTTP routes, controllers, events, commands, token flows, and
  database effects rather than isolating internals by default.
- Use unit tests only for deterministic value objects, token builders, claim bags, repositories, or similarly small pure
  units where integration coverage would make the important cases hard to see.
- For auth-engine work, bind package seams inside Testbench tests; do not depend on `../saas-starter-kit` to prove
  package behavior.
- Use the workbench app only as a Testbench consumer. Do not move reusable auth logic into `workbench/`.

## Package Architecture

- Package namespaces are `Bambamboole\LaravelOidc\Server`, `\Client`, and `\Ui` (umbrella root `Bambamboole\LaravelOidc`
  holds no code).
- Existing OIDC and Passport integration remains package-owned. New auth-engine behavior must also live in this package,
  exposed through configuration plus view/action seams that a consuming app binds.
- Keep route names and response shapes compatible with Laravel/Fortify conventions when replacing Fortify-equivalent
  behavior.
- Keep dependencies explicit and package-owned. Do not add dependencies without approval.
- Prefer Laravel primitives and existing local abstractions over new framework layers.
