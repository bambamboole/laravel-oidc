<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc;

use Bambamboole\LaravelOidc\Claims\DefaultClaimsResolver;
use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Bambamboole\LaravelOidc\Contracts\ScopeRepository;
use Bambamboole\LaravelOidc\Scopes\BridgeScopeRepository;
use Bambamboole\LaravelOidc\Scopes\PassportScopeRepository;
use Illuminate\Support\ServiceProvider;

class OidcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc.php', 'oidc');

        $this->app->singleton(ScopeRepository::class, PassportScopeRepository::class);
        $this->app->bind(\Laravel\Passport\Bridge\ScopeRepository::class, BridgeScopeRepository::class);
        $this->app->singleton(ClaimsResolver::class, DefaultClaimsResolver::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oidc.php' => config_path('oidc.php'),
        ], 'oidc-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/oidc.php');
    }
}
