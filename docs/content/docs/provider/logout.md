---
title: Logout
description: RP-initiated logout, its threat model, and OIDC back-channel logout.
---

## RP-initiated logout threat model (`/oauth/logout`)

RP-initiated logout is a known CSRF surface (a forged `GET` can log a victim out). The end-session
endpoint therefore only destroys the session when the request proves intent:

- **Valid `id_token_hint`** (signature + issuer verified) → log out and redirect to a registered
  `post_logout_redirect_uri` (or the fallback). If a user is currently logged in, the hint's `sub`
  must match the current user id, otherwise the session is left intact.
- **No valid hint + `POST`** → log out and redirect to the fallback. `POST` passes through the
  `web` guard's CSRF protection, so it is same-site.
- **No valid hint + `GET`** → **do not log out**; redirect to the fallback unchanged.

`post_logout_redirect_uri` is only honoured when it is registered on the client the hint was issued
to (stored in `oauth_clients.post_logout_redirect_uris`); otherwise the fallback
(`oidc.logout_redirect`) is used. When present, a `state` parameter is appended to the redirect.

### Residual risk (accepted by design)

`GET /oauth/authorize?max_age=0&client_id=<active client>` forces re-authentication for an
already-authenticated victim when the attacker knows an active `client_id` (public client ids are
discoverable). This is inherent to honouring `max_age` at the authorization endpoint — the effect
is a forced re-login, never account compromise.

## Back-channel logout

The provider **does** implement OIDC back-channel logout. When a session is destroyed at the
end-session endpoint, or when a session hits its absolute lifetime, the OP notifies every relying
party that participated in that session.

- Back-channel logout is **opt-in per relying-party client**: a client only receives it if it has
  registered a `backchannel_logout_uri`.
- On logout at `/oauth/logout`, the session's `sid` is resolved (from the hint's `sid` claim or the
  session), the session registry revokes it, and a logout token is dispatched to each participant.
- For sessions that expire by reaching their absolute lifetime rather than an explicit logout, the
  `oidc:dispatch-expired-session-logouts` command sends the back-channel notifications. This
  command must run ahead of context pruning so no expired session's row is removed before its
  participants are notified — see [Scheduled maintenance](/provider/scheduled-maintenance/).

Discovery advertises `backchannel_logout_supported: true` and
`backchannel_logout_session_supported: true`.
