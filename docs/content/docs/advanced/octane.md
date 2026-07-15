---
title: Octane
description: Why the id_token response type is safe on a long-lived Octane worker, and when you still need octane:reload.
---

The package is safe to run under [Laravel Octane](https://laravel.com/docs/octane).

The `id_token` response type is resolved **once** and reused across requests. It clears
its per-request state — `nonce`, `auth_time`, `amr`, `sid`, and any accumulated
`idTokenClaims` — after **each** issuance, so no state from one request leaks into a
later token minted on the same long-lived worker.

No `octane:reload` is required for this package. Reload only if your **own** singletons
hold request-scoped state.
