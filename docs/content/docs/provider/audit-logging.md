---
title: Audit logging
description: Every security-relevant event as a typed audit event, routed through a configurable sink.
---

The server package records every security-relevant event — logins, MFA challenges, consent
decisions, token issuance and revocation, client administration — as a typed `AuditEvent`. Each
event is dispatched through Laravel's event dispatcher **and** forwarded to a configurable
`AuditSink`, so you can log, persist, or ship events wherever you need.

Recording is **fail-open**: a throwing sink or listener is `report()`ed and swallowed, never
allowed to break a login or token flow. If you need stronger delivery guarantees, build them into
your own sink (e.g. queue the write).

## Event reference

Every event carries an `AuditEventType` case; the case value is a dotted string whose first
segment is the category (`auth`, `oauth`, `admin`), available via `$type->category()`.
`$type->isFailure()` marks the cases a monitoring setup usually alerts on.

| Type | Value | Context keys |
| --- | --- | --- |
| `LoginSucceeded` | `auth.login.succeeded` | `amr`, `remember` |
| `LoginFailed` | `auth.login.failed` | `method`, `reason` (`invalid_credentials`, `policy_denied`, `mfa_required_without_factor`, …), `deny_reason`, `username` |
| `Logout` | `auth.logout` | — |
| `MfaChallengeSucceeded` | `auth.mfa.challenge_succeeded` | `factor` |
| `MfaChallengeFailed` | `auth.mfa.challenge_failed` | `factor`, `reason` |
| `RecoveryCodeUsed` | `auth.mfa.recovery_code_used` | — |
| `FactorEnrollmentStarted` | `auth.mfa.factor_enrollment_started` | `factor`, `enrollment_id` |
| `FactorConfirmed` | `auth.mfa.factor_confirmed` | `factor`, `enrollment_id` |
| `FactorRevoked` | `auth.mfa.factor_revoked` | `factor`, `enrollment_id` |
| `UserRegistered` | `auth.registration.succeeded` | — |
| `PasswordReset` | `auth.password.reset` | — |
| `ConsentApproved` | `oauth.consent.approved` | `scopes` |
| `ConsentDenied` | `oauth.consent.denied` | `scopes` |
| `TokenIssued` | `oauth.token.issued` | `grant_type`, `jti`, `scopes`, `audience` (exchange only) |
| `TokenIssuanceFailed` | `oauth.token.failed` | `grant_type`, `reason` |
| `TokenRevoked` | `oauth.token.revoked` | `token_type_hint`, `jti`, `refresh_token_jti` |
| `ClientAuthenticationFailed` | `oauth.client_auth.failed` | `endpoint`, `reason` |
| `ClientRegistered` | `admin.client.registered` | `client_name`, `redirect_uris` |
| `ClientProvisioned` | `admin.client.provisioned` | `created`, `secret_rotated` |
| `KeysRotated` | `admin.keys.rotated` | `kid` |

All five grants surface as a single `TokenIssued` type — `context['grant_type']` distinguishes
`authorization_code`, `refresh_token`, `client_credentials`, `personal_access`, and
`urn:ietf:params:oauth:grant-type:token-exchange`. A refresh-token use is therefore a
`TokenIssued` with `grant_type=refresh_token`. Social and passkey logins are `LoginSucceeded`
with the method in `amr`.

## The `AuditEvent` payload

```php
final readonly class AuditEvent
{
    public AuditEventType $type;
    public ?string $userId;
    public ?string $clientId;
    public ?string $sid;          // OIDC session id, when one exists
    public ?string $ip;           // null for console-originated events
    public ?string $userAgent;    // truncated to 255 characters
    public DateTimeImmutable $occurredAt;
    public array $context;        // per-type keys, see the reference above
}
```

The recorder enriches every event centrally: IP and user agent come from the current request
(console runs produce `null`), the `sid` falls back to the active OIDC session when the call site
does not pass one explicitly.

**What is never stored:** raw access/refresh tokens or JWTs (only the `jti`), authorization
codes, client secrets, passwords, TOTP or recovery codes, and key material. One deliberate
decision to be aware of: `LoginFailed` records the **attempted username** — standard practice for
security logs, but relevant for your data-retention policy.

## Configuration

```php
'audit' => [
    'enabled' => env('OIDC_AUDIT_ENABLED', true),
    'sink' => \Bambamboole\LaravelOidc\Server\Audit\LogSink::class,
    'log_channel' => env('OIDC_AUDIT_LOG_CHANNEL'),
],
```

- `enabled` — `false` short-circuits everything: no event dispatch, no sink call.
- `sink` — class-string of an `AuditSink` implementation, resolved from the container (constructor
  dependencies are injected). The shipped sinks:
  - `LogSink` (default) writes one structured log line per event, `warning` for failure types and
    `info` otherwise, prefixed `oidc: audit <type>`.
  - `NullSink` discards everything — use it when you only want the dispatched events.
- `log_channel` — a channel from `config/logging.php` for `LogSink`; `null` uses the default
  channel. Useful to ship audit lines separately (e.g. to Loki or Datadog).

## Writing your own sink

The contract is a single method:

```php
namespace Bambamboole\LaravelOidc\Server\Contracts;

interface AuditSink
{
    public function record(AuditEvent $event): void;
}
```

A database sink is the classic choice. The package deliberately does not ship one — the table
layout, retention, and write path (inline vs. queued) are yours. A complete example:

```php
Schema::create('oidc_audit_log', function (Blueprint $table) {
    $table->id();
    $table->string('type', 64);
    $table->string('user_id')->nullable();
    $table->string('client_id')->nullable();
    $table->string('sid')->nullable();
    $table->string('ip', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->json('context');
    $table->timestamp('occurred_at');

    $table->index(['type', 'occurred_at']);
    $table->index(['user_id', 'occurred_at']);
});
```

```php
namespace App\Audit;

use Bambamboole\LaravelOidc\Server\Audit\AuditEvent;
use Bambamboole\LaravelOidc\Server\Contracts\AuditSink;
use Illuminate\Support\Facades\DB;

class EloquentAuditSink implements AuditSink
{
    public function record(AuditEvent $event): void
    {
        DB::table('oidc_audit_log')->insert([
            'type' => $event->type->value,
            'user_id' => $event->userId,
            'client_id' => $event->clientId,
            'sid' => $event->sid,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
            'context' => json_encode($event->context),
            'occurred_at' => $event->occurredAt,
        ]);
    }
}
```

```php
// config/oidc.php
'audit' => [
    'sink' => \App\Audit\EloquentAuditSink::class,
],
```

Alternatively, re-bind the contract in your own service provider — application providers register
after the package provider, so your binding wins. Prefer the config seam unless the sink needs
contextual construction:

```php
$this->app->singleton(AuditSink::class, fn () => new EloquentAuditSink);
```

Two practical notes:

- On a busy token endpoint every issuance is one sink call. If the insert latency matters,
  dispatch a queued job from `record()` instead of writing inline.
- Filtering is a sink concern, not config: `if ($event->type->category() !== 'auth') return;` is
  the whole feature.
- An audit table grows like the token tables do — plan its pruning alongside
  [Scheduled maintenance](/provider/scheduled-maintenance/).

## Listening to audit events

Every event is also dispatched through Laravel's dispatcher before the sink runs, so one-off
reactions don't need a sink at all:

```php
use Bambamboole\LaravelOidc\Server\Audit\AuditEvent;
use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;

Event::listen(AuditEvent::class, function (AuditEvent $event): void {
    match ($event->type) {
        AuditEventType::RecoveryCodeUsed => Notification::route('mail', 'security@acme.test')
            ->notify(new RecoveryCodeUsedNotification($event)),
        default => null,
    };
});
```

## Testing

The package ships an in-memory fake for host-app test suites:

```php
use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Contracts\AuditSink;
use Bambamboole\LaravelOidc\Server\Testing\FakeAuditSink;

$sink = new FakeAuditSink;
app()->instance(AuditSink::class, $sink);

// ... drive a flow ...

$sink->assertRecorded(AuditEventType::LoginSucceeded, fn ($event) => $event->userId === (string) $user->id);
$sink->assertNotRecorded(AuditEventType::LoginFailed);
$sink->assertNothingRecorded();
$sink->events(AuditEventType::TokenIssued); // list<AuditEvent>
```

## Limitations

- **`invalid_grant` raised inside league/oauth2-server at `/oauth/token`** — a replayed, expired,
  or malformed authorization code, a PKCE verifier mismatch, or a structurally invalid refresh
  token — happens before any package seam runs and emits no event, so it is not audited. These
  surface only as `400` responses to the client. Everything that flows through package code
  (refresh-context expiry, ended sessions, exchange subject-token problems, pipeline denials)
  **is** audited as `TokenIssuanceFailed`.
- **First-party session tokens** minted transparently by the self-SSO integration are not audited
  — they are re-minted on session refresh and would drown the log in noise.
- **Introspection successes** are not audited; failed client authentication at the introspection
  and revocation endpoints is.
