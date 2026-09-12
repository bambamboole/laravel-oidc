<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Sessions\Models\OidcSession;
use Bambamboole\LaravelOidc\Server\Sessions\OidcSessionRepository;
use Illuminate\Support\Str;

it('creates a session, records participants idempotently, revokes and notifies', function (): void {
    config(['oidc.session.absolute_lifetime' => 3600]);
    $registry = app(OidcSessionRepository::class);

    $sid = $registry->start('42');
    $session = $registry->find($sid);
    expect($session)->toBeInstanceOf(OidcSession::class)
        ->and($session->user_id)->toBe('42')
        ->and($session->expires_at->isFuture())->toBeTrue()
        ->and($session->revoked_at)->toBeNull();

    $first = (string) Str::uuid();
    $second = (string) Str::uuid();

    $registry->recordParticipant($sid, $first);
    $registry->recordParticipant($sid, $first);
    $registry->recordParticipant($sid, $second);
    expect($registry->participantClientIds($sid))->toEqualCanonicalizing([$first, $second]);

    $registry->revoke($sid);
    expect($registry->find($sid)->revoked_at)->not->toBeNull();

    $registry->markNotified($sid);
    expect($registry->find($sid)->logout_notified_at)->not->toBeNull();
});
