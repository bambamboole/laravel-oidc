<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Facades;

use Bambamboole\LaravelOidcClient\OidcClientManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void resolveUsersUsing(\Closure $callback)
 *
 * @see OidcClientManager
 */
class OidcClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OidcClientManager::class;
    }
}
