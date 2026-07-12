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
 * Identity routes are namespaced under `identity.*`; protocol endpoints are
 * namespaced under `oidc.*`. The relying party remains free to own Laravel's
 * conventional `login` and `logout` route names.
 */
enum Handler: string
{
    case Login = 'identity.login';
    case LoginStore = 'identity.login.store';
    case Register = 'identity.register';
    case RegisterStore = 'identity.register.store';
    case PasswordRequest = 'identity.password.request';
    case PasswordEmail = 'identity.password.email';
    case PasswordReset = 'identity.password.reset';
    case PasswordUpdate = 'identity.password.update';
    case PasswordConfirm = 'identity.password.confirm';
    case PasswordConfirmStore = 'identity.password.confirm.store';
    case PasswordConfirmation = 'identity.password.confirmation';
    case VerificationNotice = 'identity.verification.notice';
    case VerificationVerify = 'identity.verification.verify';
    case VerificationSend = 'identity.verification.send';
    case TwoFactorLogin = 'identity.two-factor.login';
    case TwoFactorLoginStore = 'identity.two-factor.login.store';
    case TwoFactorEnable = 'identity.two-factor.enable';
    case TwoFactorConfirm = 'identity.two-factor.confirm';
    case TwoFactorDisable = 'identity.two-factor.disable';
    case TwoFactorQrCode = 'identity.two-factor.qr-code';
    case TwoFactorSecretKey = 'identity.two-factor.secret-key';
    case TwoFactorRecoveryCodes = 'identity.two-factor.recovery-codes';
    case TwoFactorRegenerateRecoveryCodes = 'identity.two-factor.regenerate-recovery-codes';
    case PasskeyLoginOptions = 'identity.passkey.login-options';
    case PasskeyLogin = 'identity.passkey.login';
    case PasskeyConfirmOptions = 'identity.passkey.confirm-options';
    case PasskeyConfirm = 'identity.passkey.confirm';
    case PasskeyRegistrationOptions = 'identity.passkey.registration-options';
    case PasskeyStore = 'identity.passkey.store';
    case PasskeyDestroy = 'identity.passkey.destroy';

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
            self::PasskeyLogin,
            self::PasskeyConfirm,
            self::PasskeyStore,
            self::Introspect,
            self::Revoke,
            self::IssueToken,
            self::TokenRefresh,
            self::Approve => 'post',
            self::Deny, self::TwoFactorDisable, self::PasskeyDestroy => 'delete',
            self::Userinfo, self::Logout => ['get', 'post'],
            default => 'get',
        };
    }
}
