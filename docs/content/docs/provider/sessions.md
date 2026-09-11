---
title: Sessions
description: How the OIDC SSO session relates to Laravel's session, and what links them.
---

The package runs on **Laravel's session, unchanged**. It ships no session driver, no session store
and no session handler, and it never swaps the one your application configured. What it adds is a
second, different thing: `oidc_sessions`, the SSO session the OIDC protocol needs.

The two sit side by side. Confusing them is easy, because both are called "session" and both end at
logout.

| | Laravel's session | `oidc_sessions` |
| --- | --- | --- |
| What it is | Browser ↔ server state, behind a cookie and `SESSION_DRIVER` | The OIDC SSO session |
| Identified by | Laravel's session id | `sid`, a UUID and the table's primary key |
| Holds | Login state, CSRF token, flash data, the package's own keys below | The user, the realm, the participating clients, the absolute expiry |
| Lifetime | `config('session.lifetime')` — idle, extended by use | `oidc.session.absolute_lifetime` — absolute, from login, never extended |
| Ends when | The browser is logged out, or it goes idle | It is revoked at logout, or reaches its absolute expiry |
| Visible as | Nothing in the protocol | The `sid` claim in id tokens and logout tokens |

## What links them

Three separate mappings exist, each for a different job.

**`oidc.sid` in Laravel's session.** On the `Login` event for the identity guard, `StartOidcSession`
inserts the `oidc_sessions` row and writes its `sid` into Laravel's session. This is what the
authorize endpoint reads to stamp `sid` onto the authentication context, and what the logout listener
reads to know which SSO session to revoke.

**`session_id` on `oidc_sessions`.** The same listener records the browser session id the login ended
in. `OidcSessionRepository::findByBrowserSession()` looks a session up by it, so an account page
listing a user's browser sessions can end the OIDC session behind one without decoding the session
payload — which would break under an encrypted or differently serialized session. See
[Logout](/provider/logout/#ending-a-session-from-elsewhere).

:::caution[`session_id` is recorded once]
It is written at login and never updated. An application that regenerates the session afterwards —
on a privilege change, for instance — leaves the recorded id stale, and `findByBrowserSession()`
stops finding the row. Laravel's session guard already regenerates as part of `login()`, so a second
`regenerate()` after the guard call is both redundant and harmful.
:::

**The relying party's cache map.** A relying party is usually a different application that cannot see
the provider's tables at all. The client package therefore keeps its own `sid` → session id map in
the cache (`BackchannelLogoutStore`) so an incoming logout token can find and destroy the local
session — see [Back-channel logout](/client/backchannel-logout/).

## What the package keeps in Laravel's session

| Key | Holds |
| --- | --- |
| `oidc.sid` | The `oidc_sessions.sid` of this browser's SSO session |
| `oidc.auth_time` | When the user authenticated, for `auth_time` and `max_age` |
| `oidc.amr` | The authentication methods used, which `acr` is derived from |
| `oidc.id_token_claims`, `oidc.access_token_claims` | Claims buffered by the [post-login pipeline](/auth/post-login-pipeline/) |
| `oidc.requested_acr_values` | The `acr_values` the authorize request asked for |
| `oidc.requested_actions` | Pending [required actions](/auth/required-actions/) |
| `oidc.session_token` | The [session root token](/advanced/browser-fetch/) — a full access token |
| `oidc.authorize_request`, `oidc.consent_token` | The stashed authorize request and its consent token |
| `oidc.social.pending` | Whether a social redirect is a login or an account link |
| `oidc.webauthn.enrollment`, `login.*` | In-flight passkey enrollment and MFA challenge state |

`AuthSessionState::forget()` clears the login-derived keys after a login completes but deliberately
keeps `oidc.sid` and `oidc.auth_time`: they describe the SSO session, not one login attempt.

:::caution[Use a server-side session driver]
`oidc.session_token` is a real access token. Under `SESSION_DRIVER=cookie` the whole session payload
rides in the browser's cookie, so the token leaves the server. Use `database`, `redis` or another
server-side driver. The relying-party side needs one too, or back-channel logout cannot destroy a
session by id.
:::

## Lifetimes

The two caps are independent and enforce different things.

Laravel's `session.lifetime` governs the browser: when it lapses, the cookie's session is gone and
the user is anonymous, whatever `oidc_sessions` says.

`oidc.session.absolute_lifetime` (30 days by default) is stamped onto `oidc_sessions.expires_at` at
login and never extended. It gates two things: a refresh token whose authentication context names an
inactive session is denied with `session_ended`, and the session becomes eligible for back-channel
logout notification once it is past. It is also copied onto every authentication context the session
produces.

The authorization endpoint does **not** re-check the session's liveness. A browser whose Laravel
session is still alive past the absolute cap can still obtain an authorization code and a
short-lived access token; only the refresh is refused. With Laravel's idle lifetime normally far
shorter than the absolute cap, the browser session is usually gone long before this matters.

## Cookies and realms

Which Laravel session the provider uses depends on [realm routing](/provider/realms/#sessions):

- **`single`** — the provider shares the application's session: same cookie, same session id. Self-SSO
  depends on this, because Laravel's CSRF cookie has one fixed name.
- **`path`** — `ResolveRealm` renames the cookie to `{session.cookie}-oidc-{realm}` and scopes it to
  `/realms/{realm}` for the duration of the request, then restores both. Each realm gets its own
  browser session on the shared host.
- **`domain`** — each realm owns its origin, so the browser separates the cookies already and the
  package does nothing.

## Pruning

`oidc_sessions` and `oidc_session_participants` grow one row per login and per participating client.
Schedule `oidc:prune-sessions` and `oidc:dispatch-expired-session-logouts` — see
[Scheduled maintenance](/provider/scheduled-maintenance/).

`PurgeUser` deletes a user's session rows outright. It does not notify relying parties, so end the
sessions first when they should hear about it.
