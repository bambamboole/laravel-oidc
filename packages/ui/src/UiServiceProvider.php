<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui;

use Bambamboole\LaravelOidc\Auth\Views\ConsentView;
use Bambamboole\LaravelOidc\Auth\Views\EmailVerificationView;
use Bambamboole\LaravelOidc\Auth\Views\LoginView;
use Bambamboole\LaravelOidc\Auth\Views\PasswordConfirmationView;
use Bambamboole\LaravelOidc\Auth\Views\PasswordResetRequestView;
use Bambamboole\LaravelOidc\Auth\Views\PasswordResetView;
use Bambamboole\LaravelOidc\Auth\Views\RegisterView;
use Bambamboole\LaravelOidc\Auth\Views\TwoFactorChallengeView;
use Illuminate\Support\ServiceProvider;

/**
 * Binds this package's Lattice pages as the container implementation of
 * every auth view contract the server package declares. Bindings are set in
 * `register()`, which every package's providers complete before any
 * provider's `boot()` runs — so a host application that re-binds a contract
 * in its own provider (`register()` or `boot()`) always executes after this
 * one and wins, without forking the package.
 *
 * Lattice's own component kinds (layouts, forms, actions, tables, fragments)
 * need no registration here: this package's `composer.json` declares
 * `extra.lattice.discover: ["src"]`, so Lattice 0.25's root-manifest
 * discovery finds every attributed definition in `src/` on its own.
 *
 * The verify-email page renders a log-out link only when the route named by
 * `config('oidc-ui.logout_route')` (default `logout`) is registered; the host
 * app is responsible for defining it.
 */
class UiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oidc-ui.php', 'oidc-ui');

        $this->app->bind(LoginView::class, Pages\LoginPage::class);
        $this->app->bind(RegisterView::class, Pages\RegisterPage::class);
        $this->app->bind(PasswordResetRequestView::class, Pages\ForgotPasswordPage::class);
        $this->app->bind(PasswordResetView::class, Pages\ResetPasswordPage::class);
        $this->app->bind(EmailVerificationView::class, Pages\VerifyEmailPage::class);
        $this->app->bind(PasswordConfirmationView::class, Pages\ConfirmPasswordPage::class);
        $this->app->bind(TwoFactorChallengeView::class, Pages\TwoFactorChallengePage::class);
        $this->app->bind(ConsentView::class, Pages\OAuthConsentPage::class);
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
    }
}
