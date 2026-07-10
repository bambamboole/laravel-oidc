<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc;

use Illuminate\Support\ServiceProvider;

class OidcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc.php', 'oidc');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oidc.php' => config_path('oidc.php'),
        ], 'oidc-config');
    }
}
