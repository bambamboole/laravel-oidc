<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui;

use Illuminate\Support\ServiceProvider;

class UiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc-ui.php', 'oidc-ui');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'oidc-ui');

        $this->publishes([
            __DIR__.'/../config/oidc-ui.php' => config_path('oidc-ui.php'),
        ], 'oidc-ui-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/oidc-ui'),
        ], 'oidc-ui-lang');

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/oidc-ui'),
        ], 'oidc-ui-js');
    }
}
