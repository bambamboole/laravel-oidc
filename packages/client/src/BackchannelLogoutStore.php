<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Owns the back-channel logout bookkeeping: which local session belongs to a
 * provider sid, and which sids have been revoked. The cache key formats and
 * the retention window live here and nowhere else.
 */
class BackchannelLogoutStore
{
    public function registerSession(string $sid, string $sessionId): void
    {
        Cache::put($this->sessionKey($sid), $sessionId, $this->retention());
    }

    public function pullSessionId(string $sid): ?string
    {
        $sessionId = Cache::pull($this->sessionKey($sid));

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    public function markRevoked(string $sid): void
    {
        Cache::put($this->revokedKey($sid), true, $this->retention());
    }

    public function isRevoked(string $sid): bool
    {
        return Cache::has($this->revokedKey($sid));
    }

    private function sessionKey(string $sid): string
    {
        return "oidc-client:bclo:session:{$sid}";
    }

    private function revokedKey(string $sid): string
    {
        return "oidc-client:bclo:revoked:{$sid}";
    }

    private function retention(): DateTimeInterface
    {
        return now()->addMinutes((int) config('oidc-client.backchannel_logout.retention_minutes', 120));
    }
}
