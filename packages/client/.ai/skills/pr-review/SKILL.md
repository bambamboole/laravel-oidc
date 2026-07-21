---
name: pr-review
description: Use when reviewing a laravel-oidc pull request, branch diff, or staged/working changes for quality: reuse, simplification, efficiency, altitude, and adherence to package guidelines. Review-only: surface findings and never commit proposed changes.
---

# Laravel OIDC PR Review

Review changed code for quality and guideline adherence. This is not a broad bug hunt, but when a
touched hunk raises a concrete correctness concern, trace it to ground before deciding whether to
report it.

## Operating Rules

- Unless the user explicitly asks for an in-place review, review in a dedicated sibling worktree.
- Review-only. Never commit, push, or leave scratch edits as the deliverable.
- Scope findings to changed lines plus immediate context needed to judge them.
- Preserve behavior for cleanup suggestions. If a suggestion changes behavior, report it as a
  question or correctness finding.
- Only surface findings that are concrete and worth fixing.

## Quality Lenses

### Reuse

- Prefer existing package abstractions, Laravel primitives, Passport APIs, and local helpers.
- Do not reimplement route, broker, token, scope, claim, session, or event behavior that already
  has a package primitive.

### Simplification

- Remove dead code, redundant branches, one-caller indirection, and needless defensive checks.
- Keep explicit control flow over clever compression.

### Efficiency

- Watch for avoidable database work, repeated token parsing/signing, repeated key loading, and
  unnecessary session/token writes.
- Avoid micro-optimizations that obscure auth or OIDC behavior.

### Altitude

- Controllers should orchestrate HTTP concerns and delegate reusable behavior to package services.
- Token, OIDC, session, scope, and auth-engine concerns should stay in their existing package layer.
- Workbench code is test consumer code, not where reusable package behavior belongs.

## Guideline Checks

- Comments: no "what" comments. Delete touched comments that restate code.
- PHP: strict types, explicit parameter and return types, constructor property promotion, curly
  braces, TitleCase enum cases, PHPDoc only when it carries type/static-analysis/API value.
- Auth engine: all auth logic stays under `Bambamboole\LaravelOidc`; consuming apps bind only views,
  actions, and config.
- Tests: package behavior is verified through Testbench. Do not depend on `../saas-starter-kit` to
  prove package correctness.
- Git: conventional commits, no agent attribution, no Superpowers or agent scratch artifacts.

## Workflow

1. Establish the diff (`git diff main...HEAD`, `git diff --staged`, or `gh pr diff <n>`).
2. Read each changed hunk through the quality lenses and guideline checks.
3. For non-obvious reuse or correctness findings, grep/read enough code to verify the path.
4. Report findings only.

## Findings Output

```text
### PR Review - <branch or PR>

Found N findings.

1. [Reuse] src/Example.php:42
   Existing helper X already handles this shape. Call it instead.
```

If nothing meets the gate:

```text
### PR Review - <branch or PR>

No findings. Checked reuse, simplification, efficiency, altitude, and guideline adherence.
```
