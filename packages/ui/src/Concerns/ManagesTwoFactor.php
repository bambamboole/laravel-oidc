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

    /**
     * The enrollable provider selected via the component's `provider` context,
     * defaulting to totp so existing compositions keep working.
     */
    protected function enrollableProvider(FactorRegistry $factors): EnrollableFactorProvider
    {
        return $factors->enrollable($this->providerKey()) ?? abort(404);
    }

    protected function providerKey(): string
    {
        return (string) $this->context('provider', 'totp');
    }
}
