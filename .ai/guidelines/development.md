# Local Development

- This package is developed with Orchestra Testbench, not a full Laravel app.
- `artisan` at the repo root is a symlink to `vendor/bin/testbench`, so `php artisan <command>` boots the Testbench
  skeleton with this package's service provider and the `workbench/` app.
- Run the full package gate with `composer check`.
- The individual gates are `composer test`, `composer test:lint`, and `composer analyse`.
- Run package commands through Testbench: `php artisan <command>` or `vendor/bin/testbench <command>`.
- The first consumer for the auth-engine work is `../saas-starter-kit`, but package behavior must be implemented and
  verified in this repository's Testbench harness first.
- Regenerate `CLAUDE.md` and `AGENTS.md` after editing files in `.ai/guidelines/` with `composer boost:refresh`.

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
- If Composer dependencies are missing in a fresh checkout, run `composer install` before testing.

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

- Package code lives under `Bambamboole\LaravelOidc`.
- Existing OIDC and Passport integration remains package-owned. New auth-engine behavior must also live in this package,
  exposed through configuration plus view/action seams that a consuming app binds.
- Keep route names and response shapes compatible with Laravel/Fortify conventions when replacing Fortify-equivalent
  behavior.
- Keep dependencies explicit and package-owned. Do not add dependencies without approval.
- Prefer Laravel primitives and existing local abstractions over new framework layers.
