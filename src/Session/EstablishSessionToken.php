<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Session;

use Bambamboole\LaravelOidc\Contracts\SessionTokenProvider;
use Illuminate\Auth\Events\Login;

class EstablishSessionToken
{
    public function __construct(private readonly SessionTokenProvider $tokens) {}

    public function handle(Login $event): void
    {
        if (config('oidc.first_party_client') === null) {
            return;
        }

        $this->tokens->establish($event->user);
    }
}
