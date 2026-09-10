<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Bambamboole\LaravelOidc\Server\Shared\Authentication\RequiredActionSubject;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

trait ManagesTwoFactor
{
    /**
     * Factor providers build their own morph relations, so any Eloquent
     * authenticatable qualifies for two-factor management.
     *
     * The default guard comes first: these components are built to be mounted
     * on the host application's own account page, behind its own guard.
     * Enrollment is also reached mid-login, when a realm that insists on a
     * second factor meets a user without one — there no session exists yet by
     * design, and the subject comes from the pending login instead.
     */
    protected function twoFactorUser(): Authenticatable&Model
    {
        $user = auth()->user() ?? app(RequiredActionSubject::class)->current(request());

        abort_unless($user instanceof Model, 403);

        return $user;
    }

    protected function providerKey(): string
    {
        return (string) $this->context('provider', 'totp');
    }
}
