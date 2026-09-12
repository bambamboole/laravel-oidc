<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Sessions\Models\OidcSession;
use Bambamboole\LaravelOidc\Server\Sessions\Models\SessionParticipant;
use Bambamboole\LaravelOidc\Server\Sessions\OidcSessionRepository;
use Illuminate\Support\Str;

function pruneTestSession(string $userId, DateTimeInterface $expiresAt, ?DateTimeInterface $notifiedAt): string
{
    $repository = app(OidcSessionRepository::class);
    $sid = $repository->start($userId);

    OidcSession::query()->whereKey($sid)->update([
        'expires_at' => $expiresAt,
        'logout_notified_at' => $notifiedAt,
    ]);
    $repository->recordParticipant($sid, (string) Str::uuid());

    return $sid;
}

it('prunes sessions only once both expiry and logout notification are past the grace window', function (): void {
    $unnotified = pruneTestSession('3', now()->subDays(2), null);
    $recentlyNotified = pruneTestSession('4', now()->subDays(2), now());
    $notified = pruneTestSession('5', now()->subDays(2), now()->subDays(2));
    $recent = pruneTestSession('6', now()->subMinute(), now()->subMinute());

    $this->artisan('oidc:prune-sessions')->assertExitCode(0);

    expect(OidcSession::query()->whereKey($unnotified)->exists())->toBeTrue()
        ->and(SessionParticipant::query()->where('session_id', $unnotified)->exists())->toBeTrue()
        ->and(OidcSession::query()->whereKey($recentlyNotified)->exists())->toBeTrue()
        ->and(SessionParticipant::query()->where('session_id', $recentlyNotified)->exists())->toBeTrue()
        ->and(OidcSession::query()->whereKey($notified)->exists())->toBeFalse()
        ->and(SessionParticipant::query()->where('session_id', $notified)->exists())->toBeFalse()
        ->and(OidcSession::query()->whereKey($recent)->exists())->toBeTrue()
        ->and(SessionParticipant::query()->where('session_id', $recent)->exists())->toBeTrue();
});
