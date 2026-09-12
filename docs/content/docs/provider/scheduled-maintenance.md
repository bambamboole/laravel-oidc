---
title: Scheduled maintenance
description: The two commands you must schedule to prune the tables the token path grows and to announce expired sessions.
---

Several tables grow on the token path and are **not** cleaned up automatically:

- **The token tables** (`oidc_access_tokens`, `oidc_refresh_tokens`, `oidc_auth_codes`) grow one
  row per token issuance. With short access-token TTLs and refresh-token rotation, that's a row on
  every refresh.
- **`oidc_authentication_contexts`** grows one row per login.
- **`oidc_sessions`** and **`oidc_session_participants`** grow one row per login session and per
  participating client — see [Sessions](/provider/sessions/) for what an OIDC session is and how it
  relates to the browser's.
- **`oidc_password_reset_tokens`** grows one row per reset link requested.

Schedule **both** commands below — running only one leaves tables growing unbounded, or leaves
relying parties unnotified of expired sessions. In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('oidc:prune')->daily();
Schedule::command('oidc:dispatch-expired-session-logouts')->hourly();
```

## Dispatch and prune independently

`oidc:dispatch-expired-session-logouts` sends OIDC back-channel logout to a session's
relying-party participants and marks the eligible session as notified once it hits its absolute
lifetime (see [Logout](/provider/logout/)). `oidc:prune` removes a session only when both its
expiry and notification timestamps are older than the grace window, so the commands do not require
a specific ordering. Back-channel logout is opt-in per relying-party client: a client only receives
it if it has registered a `backchannel_logout_uri`.

## What `oidc:prune` deletes

Each model decides what of its own rows is spent, through Laravel's
[prunable models](https://laravel.com/docs/eloquent#pruning-models). `oidc:prune` runs
`model:prune` over the package's, which `model:prune` cannot discover on its own — it scans the
application's namespace. It takes `--pretend` to report what would go, and `--chunk` to size the
delete queries.

| Table | Deleted when |
| --- | --- |
| `oidc_access_tokens`, `oidc_refresh_tokens`, `oidc_auth_codes` | revoked **and** expired longer than `oidc.pruning.tokens` ago (a week by default), so a chain inside its retention window is never cut short and introspection can still read a token it just rejected |
| `oidc_authentication_contexts` | past `expires_at` — `oidc.session.absolute_lifetime` from login, the hard session cap. Once a context is gone, refreshing its tokens is denied |
| `oidc_sessions`, `oidc_session_participants` | both `expires_at` and `logout_notified_at` are older than `oidc.pruning.sessions` (a day by default). The grace keeps session rows available to queued back-channel logout jobs; an unnotified session is retained however old it is. Participants follow their session through the foreign key |
| `oidc_password_reset_tokens` | older than `oidc.tokens.password_reset`, the window a reset link is valid for |

`oidc_consents` is not pruned: a consent has no expiry and holds one row per user and client.

## Deleting everything one user, client or realm owns

Pruning is about rows that have aged out. To remove everything the package holds for a particular
subject — when a user is deleted, a client is retired, or a realm is decommissioned — use the purge
actions instead: `Purge\PurgeUser`, `Purge\PurgeClient` and `Purge\PurgeRealm`. They cascade through
the tables a foreign key cannot reach, such as the clients a user registered.
