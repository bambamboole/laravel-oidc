<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Routing;

/**
 * The canonical registry of every HTTP endpoint the package can register.
 *
 * Each case's value is the endpoint's route name and the key into the
 * `oidc.handlers` config. The HTTP verb is intrinsic to the endpoint and lives
 * on {@see self::method()} rather than in config.
 *
 * The auth-UI routes keep their conventional Laravel names (`login`,
 * `password.reset`, ...) because the framework resolves them by name — the auth
 * middleware redirects to `route('login')`, the password broker to
 * `route('password.reset')`, and so on. The protocol endpoints are namespaced
 * under `oidc.*`.
 */
enum Handler: string
{
    // Auth UI
    case Login = 'login';
    case LoginStore = 'login.store';
    case Register = 'register';
    case RegisterStore = 'register.store';
    case PasswordRequest = 'password.request';
    case PasswordEmail = 'password.email';
    case PasswordReset = 'password.reset';
    case PasswordUpdate = 'password.update';
    case PasswordConfirm = 'password.confirm';
    case PasswordConfirmStore = 'password.confirm.store';
    case PasswordConfirmation = 'password.confirmation';
    case VerificationNotice = 'verification.notice';
    case VerificationVerify = 'verification.verify';
    case VerificationSend = 'verification.send';
    case TwoFactorLogin = 'two-factor.login';
    case TwoFactorLoginStore = 'two-factor.login.store';
    case TwoFactorEnable = 'two-factor.enable';
    case TwoFactorConfirm = 'two-factor.confirm';
    case TwoFactorDisable = 'two-factor.disable';
    case TwoFactorQrCode = 'two-factor.qr-code';
    case TwoFactorSecretKey = 'two-factor.secret-key';
    case TwoFactorRecoveryCodes = 'two-factor.recovery-codes';
    case TwoFactorRegenerateRecoveryCodes = 'two-factor.regenerate-recovery-codes';

    // OIDC / OAuth protocol
    case Jwks = 'oidc.jwks';
    case Discovery = 'oidc.discovery';
    case Userinfo = 'oidc.userinfo';
    case Logout = 'oidc.logout';
    case Introspect = 'oidc.introspect';
    case Revoke = 'oidc.revoke';
    case Authorize = 'oidc.authorize';
    case IssueToken = 'oidc.token';
    case TokenRefresh = 'oidc.token.refresh';
    case Approve = 'oidc.approve';
    case Deny = 'oidc.deny';

    /**
     * Resolve this handler's configuration, or `false` when it is disabled (or
     * absent from config).
     */
    public function config(): HandlerConfig|false
    {
        /** @var array{route: string, controller: string|array{0: class-string, 1: string}, middleware?: array<int, string>}|false $config */
        $config = config('oidc.handlers', [])[$this->value] ?? false;

        if ($config === false) {
            return false;
        }

        return new HandlerConfig(
            route: $config['route'],
            controller: $config['controller'],
            middleware: $config['middleware'] ?? [],
        );
    }

    /**
     * The intrinsic HTTP verb(s) for this endpoint. An array is registered via
     * `Route::match`.
     *
     * @return string|array<int, string>
     */
    public function method(): string|array
    {
        return match ($this) {
            self::LoginStore,
            self::RegisterStore,
            self::PasswordEmail,
            self::PasswordUpdate,
            self::PasswordConfirmStore,
            self::VerificationSend,
            self::TwoFactorLoginStore,
            self::TwoFactorEnable,
            self::TwoFactorConfirm,
            self::TwoFactorRegenerateRecoveryCodes,
            self::Introspect,
            self::Revoke,
            self::IssueToken,
            self::TokenRefresh,
            self::Approve => 'post',
            self::Deny, self::TwoFactorDisable => 'delete',
            self::Userinfo, self::Logout => ['get', 'post'],
            default => 'get',
        };
    }
}
