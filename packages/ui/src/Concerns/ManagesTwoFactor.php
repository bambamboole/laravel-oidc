<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\EnrollableFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

trait ManagesTwoFactor
{
    use ResolvesAuthenticatedUser;

    /**
     * Factor providers build their own morph relations, so any Eloquent
     * authenticatable qualifies for two-factor management.
     */
    protected function twoFactorUser(): Authenticatable&Model
    {
        $user = $this->currentUser();

        abort_unless($user instanceof Model, 403);

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
