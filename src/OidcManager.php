<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Auth\UserActionManager;
use Bambamboole\LaravelOidc\Contracts\SessionTokenProvider;
use Bambamboole\LaravelOidc\Exchange\IssuedToken;
use Bambamboole\LaravelOidc\Exchange\TokenExchanger;
use Bambamboole\LaravelOidc\Hooks\ClaimHooks;
use Bambamboole\LaravelOidc\Hooks\Trigger;
use Bambamboole\LaravelOidc\Routing\Handler;
use Bambamboole\LaravelOidc\Routing\HandlerConfig;
use Closure;
use Laravel\Passport\Passport;
use RuntimeException;

class OidcManager
{
    public function __construct(
        private readonly ClaimHooks $hooks,
        private readonly SessionTokenProvider $sessionTokens,
        private readonly TokenExchanger $exchanger,
        private readonly AuthViewManager $authViews,
        private readonly UserActionManager $userActions,
    ) {}

    /**
     * The issuer URL advertised in discovery and stamped into every token.
     */
    public function issuer(): string
    {
        return Issuer::url();
    }

    /**
     * Resolve the configuration for a route handler, or `false` when it is
     * disabled (or not present in config).
     */
    public function handlerConfig(Handler $handler): HandlerConfig|false
    {
        return $handler->config();
    }

    public function loginView(Closure $view): void
    {
        $this->authViews->bind(AuthViewManager::Login, $view);
    }

    public function confirmPasswordView(Closure $view): void
    {
        $this->authViews->bind(AuthViewManager::ConfirmPassword, $view);
    }

    public function registerView(Closure $view): void
    {
        $this->authViews->bind(AuthViewManager::Register, $view);
    }

    public function requestPasswordResetLinkView(Closure $view): void
    {
        $this->authViews->bind(AuthViewManager::RequestPasswordResetLink, $view);
    }

    public function resetPasswordView(Closure $view): void
    {
        $this->authViews->bind(AuthViewManager::ResetPassword, $view);
    }

    public function verifyEmailView(Closure $view): void
    {
        $this->authViews->bind(AuthViewManager::VerifyEmail, $view);
    }

    public function createUsersUsing(callable|string $action): void
    {
        $this->userActions->createUsersUsing($action);
    }

    public function resetUserPasswordsUsing(callable|string $action): void
    {
        $this->userActions->resetUserPasswordsUsing($action);
    }

    public function onPostLogin(Closure $hook): void
    {
        $this->hooks->register(Trigger::PostLogin, $hook);
    }

    public function onRefresh(Closure $hook): void
    {
        $this->hooks->register(Trigger::Refresh, $hook);
    }

    public function onClientCredentials(Closure $hook): void
    {
        $this->hooks->register(Trigger::ClientCredentials, $hook);
    }

    public function onTokenExchange(Closure $hook): void
    {
        $this->hooks->register(Trigger::TokenExchange, $hook);
    }

    public function onUserinfo(Closure $hook): void
    {
        $this->hooks->register(Trigger::Userinfo, $hook);
    }

    /**
     * @param  string[]  $scopes
     */
    public function issueScopedToken(string $audience, array $scopes): IssuedToken
    {
        $subject = $this->sessionTokens->currentToken();

        if ($subject === null) {
            throw new RuntimeException('No session token is available for the current user.');
        }

        $client = Passport::client()->newQuery()->find(config('oidc.first_party_client'));

        if ($client === null) {
            throw new RuntimeException('The oidc.first_party_client is not configured or does not exist.');
        }

        $token = $this->exchanger->exchange($subject, $client, $audience, $scopes);

        return IssuedToken::fromEntity($token, $audience);
    }
}
