# Local Development

- This repository is a monorepo: `packages/server`, `packages/client`, and `packages/ui`, each a self-contained
  Composer project developed with Orchestra Testbench, not a full Laravel app.
- `packages/server` and `packages/client` each have an `artisan` symlink to `vendor/bin/testbench`, so `php artisan
  <command>` inside those packages boots the Testbench skeleton with the package's service provider and its
  `workbench/` app. `packages/ui` has no symlink — use `vendor/bin/testbench <command>` there.
- From the repo root, `composer install:all` installs every package, and `composer check` / `composer test` fan out
  across all three packages (`composer check:server`, `check:client`, `check:ui` for a single one).
- Inside a package, run the full gate with `composer check`; the individual gates are `composer test`,
  `composer test:lint`, and `composer analyse`.
- The first consumer for the auth-engine work is `../saas-starter-kit`, but package behavior must be implemented and
  verified in this repository's Testbench harness first.
- Each package (server, client, ui) has a `composer boost:refresh` script to regenerate its `CLAUDE.md` and
  `AGENTS.md` after editing files in `.ai/guidelines/`.

## Verification

- Before opening a PR, run the full local gate:
  ```bash
  composer check
  ```
- For narrower loops while developing:
  ```bash
  composer test
  composer test:lint
  composer analyse
  ```
- Never push on red. Use `git commit`/`git push --no-verify` only in emergencies.
- Do not add PHPStan suppressions or baselines unless the user explicitly approves them.
- If Composer dependencies are missing in a fresh checkout, run `composer install:all` from the repo root before
  testing.

## Comments

- Code must be self-explanatory: reach for clear names, small functions, and types before a comment.
- Do not add comments. A comment is a last resort and explains only *why* something is done, never *what* the code does.
- When you encounter an obsolete, redundant, or "what" comment, delete it.
- Delete section banners and navigation comments unless they explain a non-obvious boundary.
- Delete comments that narrate the next line, assertion, or obvious test setup; prefer clearer test names and variable names.
- Keep PHPDoc/JSDoc only when it carries type information, public API intent, static-analysis value, generated-file context,
  or a non-obvious constraint.
- Keep comments that explain framework quirks, ordering requirements, browser/test timing, cache/build behavior, performance
  traps, or other constraints that are hard to infer from the code alone.

## Testing

- Prefer feature tests for package behavior. Test through HTTP routes, controllers, events, commands, token flows, and
  database effects rather than isolating internals by default.
- Use unit tests only for deterministic value objects, token builders, claim bags, repositories, or similarly small pure
  units where integration coverage would make the important cases hard to see.
- For auth-engine work, bind package seams inside Testbench tests; do not depend on `../saas-starter-kit` to prove package
  behavior.
- Use the workbench app only as a Testbench consumer. Do not move reusable auth logic into `workbench/`.

## Package Architecture

- Package namespaces are `Bambamboole\LaravelOidc\Server`, `\Client`, and `\Ui` (umbrella root `Bambamboole\LaravelOidc` holds no code).
- Existing OIDC and Passport integration remains package-owned. New auth-engine behavior must also live in this package,
  exposed through configuration plus view/action seams that a consuming app binds.
- Keep route names and response shapes compatible with Laravel/Fortify conventions when replacing Fortify-equivalent
  behavior.
- Keep dependencies explicit and package-owned. Do not add dependencies without approval.
- Prefer Laravel primitives and existing local abstractions over new framework layers.
