<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\EnrollableFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\FactorAuthenticatable;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;

trait ManagesTwoFactor
{
    use ResolvesAuthenticatedUser;

    protected function twoFactorUser(): FactorAuthenticatable
    {
        $user = $this->currentUser();

        abort_unless($user instanceof FactorAuthenticatable, 403);

        return $user;
    }

    protected function totpEnrollable(FactorRegistry $factors): EnrollableFactorProvider
    {
        return $factors->enrollable('totp') ?? abort(404);
    }
}
