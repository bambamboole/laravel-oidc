<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc;

use Bambamboole\LaravelOidc\Hooks\ClaimHooks;
use Bambamboole\LaravelOidc\Hooks\Trigger;
use Closure;

class OidcManager
{
    public function __construct(private readonly ClaimHooks $hooks) {}

    public function onPostLogin(Closure $hook): void
    {
        $this->hooks->register(Trigger::PostLogin, $hook);
    }

    public function onRefresh(Closure $hook): void
    {
        $this->hooks->register(Trigger::Refresh, $hook);
    }

    public function onClientCredentials(Closure $hook): void
    {
        $this->hooks->register(Trigger::ClientCredentials, $hook);
    }

    public function onTokenExchange(Closure $hook): void
    {
        $this->hooks->register(Trigger::TokenExchange, $hook);
    }

    public function onUserinfo(Closure $hook): void
    {
        $this->hooks->register(Trigger::Userinfo, $hook);
    }
}
