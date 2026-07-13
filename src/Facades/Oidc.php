<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Facades;

use Bambamboole\LaravelOidc\OidcManager;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void onClientCredentials(Closure $hook)
 * @method static void onTokenExchange(Closure $hook)
 * @method static void onUserinfo(Closure $hook)
 * @method static void postLogin(Closure $hook)
 * @method static void loginView(Closure $view)
 * @method static void confirmPasswordView(Closure $view)
 * @method static void registerView(Closure $view)
 * @method static void requestPasswordResetLinkView(Closure $view)
 * @method static void resetPasswordView(Closure $view)
 * @method static void verifyEmailView(Closure $view)
 * @method static void twoFactorChallengeView(Closure $view)
 * @method static void createUsersUsing(callable|string $action)
 * @method static void resetUserPasswordsUsing(callable|string $action)
 * @method static \Bambamboole\LaravelOidc\Clients\FirstPartyClientProvisioningResult provisionFirstPartyClient(string $name, string[] $redirectUris, string[] $postLogoutRedirectUris = [], string[] $allowedExchangeAudiences = [], ?string $adoptClientId = null, bool $rotateSecret = false)
 * @method static \Bambamboole\LaravelOidc\Exchange\IssuedToken issueScopedToken(string $audience, string[] $scopes)
 * @method static string issuer()
 * @method static \Bambamboole\LaravelOidc\Routing\HandlerConfig|false handlerConfig(\Bambamboole\LaravelOidc\Routing\Handler $handler)
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
