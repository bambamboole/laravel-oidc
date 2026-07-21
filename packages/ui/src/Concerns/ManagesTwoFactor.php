<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Bambamboole\LaravelOidc\Auth\MultiFactor\Contracts\FactorAuthenticatable;

trait ManagesTwoFactor
{
    use ResolvesAuthenticatedUser;

    protected function twoFactorUser(): FactorAuthenticatable
    {
        $user = $this->currentUser();

        abort_unless($user instanceof FactorAuthenticatable, 403);

        return $user;
    }
}
