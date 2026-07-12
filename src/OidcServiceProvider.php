<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Auth\UserActionManager;
use Bambamboole\LaravelOidc\Claims\DefaultClaimsResolver;
use Bambamboole\LaravelOidc\Console\RotateKeysCommand;
use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Bambamboole\LaravelOidc\Contracts\ExchangePolicy;
use Bambamboole\LaravelOidc\Contracts\ScopeRepository;
use Bambamboole\LaravelOidc\Contracts\SessionTokenProvider;
use Bambamboole\LaravelOidc\Exchange\DefaultExchangePolicy;
use Bambamboole\LaravelOidc\Exchange\TokenExchanger;
use Bambamboole\LaravelOidc\Grant\OidcAuthCodeGrant;
use Bambamboole\LaravelOidc\Grant\TokenExchangeGrant;
use Bambamboole\LaravelOidc\Hooks\AccessTokenHookRunner;
use Bambamboole\LaravelOidc\Hooks\ClaimHooks;
use Bambamboole\LaravelOidc\Http\Controllers\AuthorizationController;
use Bambamboole\LaravelOidc\Listeners\RecordAuthTime;
use Bambamboole\LaravelOidc\Responses\IdTokenResponse;
use Bambamboole\LaravelOidc\Scopes\BridgeScopeRepository;
use Bambamboole\LaravelOidc\Scopes\PassportScopeRepository;
use Bambamboole\LaravelOidc\Session\EstablishSessionToken;
use Bambamboole\LaravelOidc\Session\ForgetSessionToken;
use Bambamboole\LaravelOidc\Session\SessionMintTokenProvider;
use Bambamboole\LaravelOidc\Token\AccessTokenMinter;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use DateInterval;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository as PassportBridgeScopeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;

class OidcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc.php', 'oidc');

        Passport::ignoreRoutes();
        Passport::useAccessTokenEntity(OidcAccessToken::class);

        $this->app->singleton(ScopeRepository::class, PassportScopeRepository::class);
        $this->app->bind(PassportBridgeScopeRepository::class, BridgeScopeRepository::class);
        $this->app->singleton(ClaimsResolver::class, DefaultClaimsResolver::class);
        $this->app->singleton(ClaimHooks::class);
        $this->app->singleton(AuthViewManager::class);
        $this->app->singleton(UserActionManager::class);
        $this->app->singleton(AccessTokenHookRunner::class);
        $this->app->singleton(OidcManager::class);
        $this->app->singleton(ExchangePolicy::class, DefaultExchangePolicy::class);
        $this->app->singleton(AccessTokenMinter::class);
        $this->app->singleton(TokenExchanger::class);
        $this->app->singleton(SessionTokenProvider::class, SessionMintTokenProvider::class);

        $this->app->when(AuthorizationController::class)
            ->needs(StatefulGuard::class)
            ->give(fn () => Auth::guard(config('passport.guard', null)));

        $this->app->extend(AuthorizationServer::class, function (AuthorizationServer $server, Application $app): AuthorizationServer {
            $grant = new OidcAuthCodeGrant(
                $app->make(AuthCodeRepository::class),
                $app->make(RefreshTokenRepository::class),
                new DateInterval('PT10M'),
            );
            $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());

            $server->enableGrantType($grant, Passport::tokensExpireIn());

            if (config('oidc.token_exchange.enabled', true)) {
                $server->enableGrantType(
                    new TokenExchangeGrant(
                        $app->make(TokenExchanger::class),
                    ),
                    Passport::tokensExpireIn(),
                );
            }

            return $server;
        });
    }

    public function boot(): void
    {
        Passport::useAuthorizationServerResponseType($this->app->make(IdTokenResponse::class));

        Event::listen(Login::class, RecordAuthTime::class);
        Event::listen(Login::class, EstablishSessionToken::class);
        Event::listen(Logout::class, ForgetSessionToken::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/oidc.php');

        $this->publishes([
            __DIR__.'/../config/oidc.php' => config_path('oidc.php'),
        ], 'oidc-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'oidc-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([RotateKeysCommand::class]);
        }
    }
}
