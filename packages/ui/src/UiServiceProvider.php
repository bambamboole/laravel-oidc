<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Ui\Layouts\AuthLayout;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Lattice\Lattice\Actions\ActionRegistry;
use Lattice\Lattice\Forms\FormRegistry;
use Lattice\Lattice\Fragments\FragmentRegistry;
use Lattice\Lattice\Layouts\LayoutRegistry;
use Lattice\Lattice\Tables\TableRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds this package's Lattice pages as the default renderers for every
 * `AuthViewManager` seam the server package exposes. Package providers boot
 * before app providers, so a host application that re-binds a view in its own
 * provider wins — that is the intended override mechanism.
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
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'oidc-ui');

        // Registered explicitly rather than relying on Lattice's filesystem
        // discovery, which only scans `config('lattice.discover')` paths
        // (the host app's `app/` directory by default) and would never see
        // this package's src/ directory.
        $this->app->make(LayoutRegistry::class)->register(AuthLayout::class);

        $this->app->make(FormRegistry::class)->register(Forms\ConfirmTwoFactorForm::class);

        $this->app->make(ActionRegistry::class)->register([
            Actions\EnableTwoFactorAuthenticationAction::class,
            Actions\DisableTwoFactorAuthenticationAction::class,
            Actions\RegenerateRecoveryCodesAction::class,
            Actions\DeletePasskeyAction::class,
            Actions\SendVerificationEmailAction::class,
        ]);

        $this->app->make(TableRegistry::class)->register(Tables\PasskeysTable::class);

        $this->app->make(FragmentRegistry::class)->register(Fragments\TwoFactorSetupFragment::class);

        $views = $this->app->make(AuthViewManager::class);
        $views->bind(AuthViewManager::Login, fn () => new Pages\LoginPage);
        $views->bind(AuthViewManager::Register, fn () => new Pages\RegisterPage);
        $views->bind(AuthViewManager::RequestPasswordResetLink, fn () => new Pages\ForgotPasswordPage);
        $views->bind(AuthViewManager::ResetPassword, fn () => new Pages\ResetPasswordPage);
        $views->bind(AuthViewManager::VerifyEmail, fn () => new Pages\VerifyEmailPage);
        $views->bind(AuthViewManager::ConfirmPassword, fn () => new Pages\ConfirmPasswordPage);
        $views->bind(AuthViewManager::TwoFactorChallenge, fn () => new Pages\TwoFactorChallengePage);

        Passport::authorizationView(
            fn (array $parameters): Response => (new Pages\OAuthConsentPage(
                client: $parameters['client'],
                user: $parameters['user'],
                scopes: $parameters['scopes'],
                authToken: $parameters['authToken'],
            ))->toResponse(Request::instance()),
        );

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
