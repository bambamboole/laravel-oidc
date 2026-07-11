<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Session;

use Bambamboole\LaravelOidc\Contracts\SessionTokenProvider;
use Illuminate\Auth\Events\Login;
use Throwable;

class EstablishSessionToken
{
    public function __construct(private readonly SessionTokenProvider $tokens) {}

    public function handle(Login $event): void
    {
        if (blank(config('oidc.first_party_client'))) {
            return;
        }

        try {
            $this->tokens->establish($event->user);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
