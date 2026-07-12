---
name: worktrees
description: Use when creating, preparing, listing, switching between, or removing git worktrees for the laravel-oidc package. Covers safe multi-agent branch isolation, sibling worktree layout, Testbench setup, verification gates, and cleanup.
---

# Laravel OIDC Worktrees

This repository is a Laravel package developed with Orchestra Testbench, not a full Laravel app.
There is no Herd site, app `.env`, key generation, or manual migration ritual. Testbench provisions
the workbench skeleton and SQLite database as needed.

Use a worktree whenever you need isolation from another agent's work. If the user explicitly asks to
stay in the main checkout, follow that instruction.

## Layout

Worktrees live as siblings of the main checkout, directly under its parent directory:

```text
/Users/bambamboole/Projects/
  laravel-oidc/
  laravel-oidc-<slug>/
```

Do not nest worktrees inside the repo.

## Take Stock

Before creating a worktree:

```bash
git worktree list
git status --short
git branch --show-current
```

- If there are already many worktrees, surface the list instead of silently adding another.
- Treat uncommitted changes you did not make as another agent's work.
- Never remove another agent's worktree or branch.

## Create

```bash
git fetch origin --prune
git worktree add ../laravel-oidc-<slug> -b <branch> main
cd ../laravel-oidc-<slug>
```

## Set Up

```bash
composer install
```

Refresh Boost-generated guidance if needed:

```bash
composer boost:refresh
```

## Verify

Run the package gate before reporting completion:

```bash
composer check
```

Use narrower loops while iterating:

```bash
composer test
composer test:lint
composer analyse
```

## Remove

Only remove a worktree that belongs to your task and has no needed changes.

```bash
cd /Users/bambamboole/Projects/laravel-oidc
git -C ../laravel-oidc-<slug> status --short
git worktree remove ../laravel-oidc-<slug>
git worktree prune
```

Never use `git worktree remove --force` unless the user explicitly says to discard that worktree's
uncommitted changes.
