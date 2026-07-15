---
title: Scheduled maintenance
description: The commands you must schedule to prune token tables and announce expired sessions.
---

Several tables grow on the token path and are **not** cleaned up automatically — you must schedule
their pruning yourself:

- **Passport's tables** (`oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`) grow
  one row per token issuance. With short access-token TTLs and refresh-token rotation, that's a row
  on every refresh. Prune them with Passport's `passport:purge`.
- **This package's tables** grow too: `oidc_authentication_contexts` (one row per login) and
  `oidc_access_token_contexts` (one row per access-token issuance). Prune them with
  `oidc:prune-authentication-contexts`.

Schedule **all three** — running only some leaves tables growing unbounded, or leaves relying
parties unnotified of expired sessions. In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('passport:purge')->daily();
Schedule::command('oidc:dispatch-expired-session-logouts')->hourly();
Schedule::command('oidc:prune-authentication-contexts')->daily();
```

## Order matters: dispatch before prune

`oidc:dispatch-expired-session-logouts` sends OIDC back-channel logout to a session's
relying-party participants once the session hits its absolute lifetime (see
[Logout](/provider/logout/)). It must run **ahead of** `oidc:prune-authentication-contexts`, which
deletes `oidc_sessions` rows only after a grace window — scheduling dispatch first (and more
frequently) ensures every expired session is announced before its row is removed. Back-channel
logout is opt-in per relying-party client: a client only receives it if it has registered a
`backchannel_logout_uri`.

## What prune deletes

`oidc:prune-authentication-contexts` deletes:

- `oidc_authentication_contexts` rows past their `expires_at` (i.e. past
  `oidc.session.absolute_lifetime` from login — the hard session cap). Once a context is gone,
  refreshing its tokens is denied.
- `oidc_access_token_contexts` link rows older than `oidc.session.absolute_lifetime` **plus** the
  refresh-token lifetime, so a still-rotating refresh chain never loses its link early (which would
  silently drop the deny-on-expiry cap). Retention is fully self-managed here and does **not**
  depend on how `passport:purge` is configured.

For large deployments, note that Passport's `oauth_refresh_tokens.expires_at` is unindexed
upstream, so `passport:purge`'s expiry scan can be slow at scale — add an index via your own
migration if that becomes a bottleneck.
