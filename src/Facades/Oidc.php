<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Facades;

use Bambamboole\LaravelOidc\OidcManager;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void onPostLogin(Closure $hook)
 * @method static void onRefresh(Closure $hook)
 * @method static void onClientCredentials(Closure $hook)
 * @method static void onTokenExchange(Closure $hook)
 * @method static void onUserinfo(Closure $hook)
 * @method static void loginView(Closure $view)
 * @method static void confirmPasswordView(Closure $view)
 * @method static void registerView(Closure $view)
 * @method static void requestPasswordResetLinkView(Closure $view)
 * @method static void resetPasswordView(Closure $view)
 * @method static void verifyEmailView(Closure $view)
 * @method static void createUsersUsing(callable|string $action)
 * @method static void resetUserPasswordsUsing(callable|string $action)
 * @method static \Bambamboole\LaravelOidc\Exchange\IssuedToken issueScopedToken(string $audience, string[] $scopes)
 *
 * @see OidcManager
 */
class Oidc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OidcManager::class;
    }
}
