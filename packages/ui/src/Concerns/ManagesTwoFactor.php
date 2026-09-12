<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Concerns;

use Bambamboole\LaravelOidc\Ui\Support\ScreenSubject;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

trait ManagesTwoFactor
{
    protected function twoFactorUser(): Authenticatable&Model
    {
        return ScreenSubject::currentOrFail();
    }

    protected function providerKey(): string
    {
        return (string) $this->context('provider', 'totp');
    }
}
