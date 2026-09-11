---
title: Audit logging
description: Security-relevant domain events, recorded through a configurable sink.
---

Every security-relevant step in the server package — logins, MFA challenges, consent decisions,
token issuance and revocation, client administration — dispatches a domain event that implements
`Bambamboole\LaravelOidc\Server\Shared\Audit\AuditEvent`. The Audit domain listens for that
contract, enriches the event's `AuditRecord` with request context and hands it to the configured
`AuditSink`. Domains never talk to the sink themselves.

Recording is **fail-open**: a throwing sink is `report()`ed and swallowed, never allowed to break
a login or token flow. If you need stronger delivery guarantees, build them into your own sink
(e.g. queue the write).

## Event reference

Every audit event carries its type on the contract as `$event->type`; the package's own events use
the `Shared\Audit\AuditEventType` enum, whose value is a dotted string with the category
(`auth`, `oauth`, `admin`) as its first segment, available via `$record->category()`.
`$record->failure` marks the records a monitoring setup usually alerts on.

| Event | Type | Context keys |
| --- | --- | --- |
| `Authentication\Events\LoginSucceeded` | `auth.login.succeeded` | `amr`, `remember` |
| `Authentication\Events\LoginFailed` | `auth.login.failed` | `method`, `reason` (`invalid_credentials`, `policy_denied`, `mfa_required_without_factor`, …), `deny_reason`, `username` |
| `Brokering\Events\SocialLoginFailed` | `auth.login.failed` | `method` (`social:<provider>`), `reason` |
| `Authentication\Events\LoggedOut` | `auth.logout` | — |
| `Authentication\Events\UserRegistered` | `auth.registration.succeeded` | — |
| `Authentication\Events\PasswordReset` | `auth.password.reset` | — |
| `Credentials\Events\MfaChallengeSucceeded` | `auth.mfa.challenge_succeeded` | `factor` |
| `Credentials\Events\MfaChallengeFailed` | `auth.mfa.challenge_failed` | `factor`, `reason` |
| `Credentials\Events\RecoveryCodeUsed` | `auth.mfa.recovery_code_used` | — |
| `Credentials\Events\FactorEnrollmentStarted` | `auth.mfa.factor_enrollment_started` | `factor`, `enrollment_id` |
| `Credentials\Events\FactorConfirmed` | `auth.mfa.factor_confirmed` | `factor`, `enrollment_id` |
| `Credentials\Events\FactorRevoked` | `auth.mfa.factor_revoked` | `factor`, `enrollment_id` |
| `Consents\Events\ConsentApproved` | `oauth.consent.approved` | `scopes` |
| `Consents\Events\ConsentDenied` | `oauth.consent.denied` | `scopes` |
| `Tokens\Events\TokenIssued` | `oauth.token.issued` | `grant_type`, `jti`, `scopes`, `audiences` |
| `Tokens\Events\TokenIssuanceFailed` | `oauth.token.failed` | `grant_type`, `reason`, `deny_reason`, `scope` |
| `Tokens\Events\TokenRevoked` | `oauth.token.revoked` | `token_type`, `jti`, `refresh_token_jti` |
| `Protocol\Events\ClientAuthenticationFailed` | `oauth.client_auth.failed` | `endpoint`, `reason` |
| `Clients\Events\ClientRegistered` | `admin.client.registered` | `client_name`, `redirect_uris`, `token_endpoint_auth_method` |
| `Clients\Events\ClientProvisioned` | `admin.client.provisioned` | `created`, `secret_rotated` |
| `Keys\Events\KeysRotated` | `admin.keys.rotated` | `kid` |

Class names are relative to `Bambamboole\LaravelOidc\Server`. The events carry the same data as
typed properties, so a listener reads `$event->grantType` instead of a context key.

All five grants surface as a single `TokenIssued` event — `grantType` distinguishes
`authorization_code`, `refresh_token`, `client_credentials`, `personal_access`, and
`urn:ietf:params:oauth:grant-type:token-exchange`. A refresh-token use is therefore a
`TokenIssued` with `grant_type=refresh_token`. Social and passkey logins are `LoginSucceeded`
with the method in `amr`. `LoginSucceeded` is raised for interactive logins only; a session
restored from a remember-me cookie is not audited.

## The `AuditRecord` payload

```php
final readonly class AuditRecord
{
    public string $type;              // dotted, e.g. oauth.token.issued
    public ?string $userId;
    public ?string $clientId;
    public ?string $sid;              // OIDC session id, when one exists
    public array $context;            // per-type keys, see the reference above
    public bool $failure;
    public ?string $ip;               // null for console-originated events
    public ?string $userAgent;        // truncated to 255 characters
    public DateTimeImmutable $occurredAt;
}
```

The event builds the record with what its domain knows. The Audit listener adds IP and user
agent from the current request (console runs produce `null`) and falls back to the active OIDC
session's `sid` when the event does not carry one. Null context values are dropped.

**What is never stored:** raw access/refresh tokens or JWTs (only the `jti`), authorization
codes, client secrets, passwords, TOTP or recovery codes, and key material. One deliberate
decision to be aware of: `LoginFailed` records the **attempted username** — standard practice for
security logs, but relevant for your data-retention policy.

## Configuration

```php
'audit' => [
    'enabled' => env('OIDC_AUDIT_ENABLED', true),
    'sink' => \Bambamboole\LaravelOidc\Server\Audit\Sinks\LogAuditSink::class,
    'log_channel' => env('OIDC_AUDIT_LOG_CHANNEL'),
],
```

- `enabled` — `false` stops recording. The domain events are still dispatched, so your own
  listeners keep working.
- `sink` — class-string of an `AuditSink` implementation, resolved from the container (constructor
  dependencies are injected). The shipped sinks:
  - `LogAuditSink` (default) writes one structured log line per record, `warning` for failures and
    `info` otherwise, prefixed `oidc: audit <type>`.
  - `NullAuditSink` discards everything — use it when you only listen to the events.
- `log_channel` — a channel from `config/logging.php` for `LogAuditSink`; `null` uses the default
  channel. Useful to ship audit lines separately (e.g. to Loki or Datadog).

## Writing your own sink

The contract is a single method:

```php
namespace Bambamboole\LaravelOidc\Server\Shared\Audit;

interface AuditSink
{
    public function record(AuditRecord $record): void;
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
    $table->boolean('failure');
    $table->json('context');
    $table->timestamp('occurred_at');

    $table->index(['type', 'occurred_at']);
    $table->index(['user_id', 'occurred_at']);
});
```

```php
namespace App\Audit;

use Bambamboole\LaravelOidc\Server\Shared\Audit\AuditRecord;
use Bambamboole\LaravelOidc\Server\Shared\Audit\AuditSink;
use Illuminate\Support\Facades\DB;

class EloquentAuditSink implements AuditSink
{
    public function record(AuditRecord $record): void
    {
        DB::table('oidc_audit_log')->insert([
            'type' => $record->type,
            'user_id' => $record->userId,
            'client_id' => $record->clientId,
            'sid' => $record->sid,
            'ip' => $record->ip,
            'user_agent' => $record->userAgent,
            'failure' => $record->failure,
            'context' => json_encode($record->context),
            'occurred_at' => $record->occurredAt,
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
- Filtering is a sink concern, not config: `if ($record->category() !== 'auth') return;` is
  the whole feature.
- An audit table grows like the token tables do — plan its pruning alongside
  [Scheduled maintenance](/provider/scheduled-maintenance/).

## Listening to events

The events are ordinary Laravel events, so a one-off reaction listens to the class it cares
about and never needs a sink:

```php
use Bambamboole\LaravelOidc\Server\Credentials\Events\RecoveryCodeUsed;

Event::listen(RecoveryCodeUsed::class, function (RecoveryCodeUsed $event): void {
    Notification::route('mail', 'security@acme.test')
        ->notify(new RecoveryCodeUsedNotification($event->userId));
});
```

Listening to `AuditEvent::class` receives every audited event, whichever domain raised it.

## Raising your own audit events

Any event implementing `AuditEvent` is recorded through the same listener and sink, so
application-level actions can share the audit trail:

```php
use Bambamboole\LaravelOidc\Server\Shared\Audit\AuditEvent;
use Bambamboole\LaravelOidc\Server\Shared\Audit\AuditRecord;

final class ExportDownloaded implements AuditEvent
{
    public string|BackedEnum $type { get => AppAuditType::ExportDownloaded; }

    public function __construct(public readonly string $userId, public readonly string $file) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->type, userId: $this->userId, context: ['file' => $this->file]);
    }
}

event(new ExportDownloaded($user->id, 'report.csv'));
```

`$type` takes your own backed enum or a plain string; `AuditRecord` and `FakeAuditSink` store and
compare an enum as its value. PHP does not allow a hooked property in a `readonly class`, so mark
the constructor parameters `readonly` instead.

## Testing

The package ships an in-memory fake for host-app test suites:

```php
use Bambamboole\LaravelOidc\Server\Shared\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Shared\Audit\AuditSink;
use Bambamboole\LaravelOidc\Server\Testing\FakeAuditSink;

$sink = new FakeAuditSink;
app()->instance(AuditSink::class, $sink);

// ... drive a flow ...

$sink->assertRecorded(AuditEventType::LoginSucceeded, fn ($record) => $record->userId === (string) $user->id);
$sink->assertNotRecorded(AuditEventType::LoginFailed);
$sink->assertNothingRecorded();
$sink->records(AuditEventType::TokenIssued); // list<AuditRecord>
```

`Event::fake()` works too, but it also silences the recording listener; assert on the sink when a
test cares about what reaches the audit trail, and on the dispatched event when it cares about
the domain.

## Limitations

- **Malformed token requests at `/oauth/token`** — an unknown, expired, or
  foreign authorization code, a PKCE verifier mismatch, or an unknown or expired refresh token —
  are rejected before any issuance step runs and emit no event. These surface only as `400`
  responses to the client. A replayed authorization code and a reused refresh token **are**
  audited as `TokenIssuanceFailed` (`code_replayed`, `refresh_token_reused`), as is everything
  that fails inside issuance: refresh-context expiry, ended sessions, exchange subject-token
  problems, and pipeline denials.
- **First-party session tokens** minted transparently by the self-SSO integration are not audited
  — they are re-minted on session refresh and would drown the log in noise.
- **Introspection successes** are not audited; failed client authentication at the introspection
  and revocation endpoints is.
