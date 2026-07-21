<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;

trait ResolvesAuthenticatedUser
{
    protected function currentUser(): Authenticatable
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        return $user;
    }
}
