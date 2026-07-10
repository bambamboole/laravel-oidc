<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Listeners;

use Illuminate\Auth\Events\Login;

class RecordAuthTime
{
    public function handle(Login $event): void
    {
        if (app()->bound('session.store') && app('session.store')->isStarted()) {
            app('session.store')->put('oidc.auth_time', time());
        }
    }
}
