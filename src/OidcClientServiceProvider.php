<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Http\Middleware\EnforceBackchannelLogout;
use Bambamboole\LaravelOidcClient\Token\IdTokenValidator;
use Bambamboole\LaravelOidcClient\Token\JwksKeyResolver;
use Bambamboole\LaravelOidcClient\Token\LogoutTokenValidator;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class OidcClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc-client.php', 'oidc-client');

        $this->app->singleton(OidcClientManager::class);
        $this->app->singleton(OidcDiscovery::class);
        $this->app->singleton(JwksKeyResolver::class);
        $this->app->singleton(IdTokenValidator::class);
        $this->app->singleton(LogoutTokenValidator::class);
        $this->app->singleton(RelyingParty::class);
    }

    public function boot(): void
    {
        if (config('oidc-client.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/oidc-client.php');
        }

        if (config('oidc-client.backchannel_logout.enabled', false)) {
            $router = $this->app['router'];
            $router->aliasMiddleware('oidc-client.enforce-logout', EnforceBackchannelLogout::class);

            if (config('oidc-client.backchannel_logout.auto_middleware', true)) {
                // Appending through the Kernel (rather than pushing directly onto the
                // Router) is required: the HTTP Kernel's constructor overwrites the
                // Router's middleware groups from its own $middlewareGroups property
                // the first time it is resolved, which would silently wipe a push made
                // straight against the Router before that first resolution.
                $this->app->make(Kernel::class)->appendMiddlewareToGroup(
                    (string) config('oidc-client.backchannel_logout.middleware_group', 'web'),
                    EnforceBackchannelLogout::class,
                );
            }
        }

        $this->publishes([
            __DIR__.'/../config/oidc-client.php' => config_path('oidc-client.php'),
        ], 'oidc-client-config');
    }
}
