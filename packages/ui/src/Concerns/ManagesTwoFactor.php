<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

trait ManagesTwoFactor
{
    /**
     * Factor providers build their own morph relations, so any Eloquent
     * authenticatable qualifies for two-factor management.
     */
    protected function twoFactorUser(): Authenticatable&Model
    {
        $user = auth()->user();

        abort_unless($user instanceof Model, 403);

        return $user;
    }

    protected function providerKey(): string
    {
        return (string) $this->context('provider', 'totp');
    }
}
