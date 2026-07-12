---
name: documentation
description: Use when writing or editing laravel-oidc documentation, including README.md, docs/*.md, package handoff notes, design docs, and consumer integration guidance.
---

# Laravel OIDC Documentation

This package's documentation is plain Markdown in `README.md` and `docs/**`.

## Authoring

- Keep docs concise and package-centered.
- Distinguish package responsibilities from consuming-app responsibilities. The package owns controllers, routes, OIDC
  behavior, auth-engine behavior, and reusable seams. Consumers bind views/actions/configuration.
- When documenting behavior parity, name the source of truth: Laravel core, Passport, Fortify parity, OIDC Core, OAuth 2.1,
  RFC 8414, RFC 9068, RFC 7009, or the approved design docs.
- Use exact route names, config keys, facade methods, event names, and response shapes.
- Avoid speculative future wording unless the document is explicitly a design or plan.
- Do not commit local agent plans/specs under `docs/superpowers/`; those are scratch artifacts and are git-ignored.

## Verification

- For docs-only changes, run the lightest relevant check:
  ```bash
  composer test:lint
  ```
- If docs describe generated behavior, routes, config, or public APIs, run a focused test or `composer check` to confirm the
  documented surface still exists.
- If docs include command examples, prefer commands that work in the package Testbench context.
