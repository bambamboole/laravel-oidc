<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Console;

use Bambamboole\LaravelOidc\Auth\Models\AccessTokenContext;
use Bambamboole\LaravelOidc\Auth\Models\AuthenticationContext;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Laravel\Passport\Passport;

class PruneAuthenticationContextsCommand extends Command
{
    protected $signature = 'oidc:prune-authentication-contexts';

    protected $description = 'Delete expired OIDC authentication contexts and stale access-token links.';

    public function handle(): int
    {
        $contexts = AuthenticationContext::query()->where('expires_at', '<', now())->delete();

        // Link rows outlive their context so refresh can distinguish "expired" from "never linked".
        // Retain them until no live refresh token could reference them: absolute + refresh idle window.
        $idleSeconds = (new DateTimeImmutable)->add(Passport::refreshTokensExpireIn())->getTimestamp()
            - (new DateTimeImmutable)->getTimestamp();
        $horizon = now()->subSeconds((int) config('oidc.session.absolute_lifetime') + $idleSeconds);
        $links = AccessTokenContext::query()->where('created_at', '<', $horizon)->delete();

        $this->info("Pruned {$contexts} context(s) and {$links} link(s).");

        return self::SUCCESS;
    }
}
