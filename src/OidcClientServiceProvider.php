<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use Illuminate\Support\ServiceProvider;

class OidcClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc-client.php', 'oidc-client');

        $this->app->singleton(OidcClientManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oidc-client.php' => config_path('oidc-client.php'),
        ], 'oidc-client-config');
    }
}
