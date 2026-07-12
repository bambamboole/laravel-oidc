<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Routing;

/**
 * The canonical registry of every HTTP endpoint the package can register.
 *
 * Each case's value is the endpoint's route name and the key into the
 * `oidc.handlers` config. The HTTP verb is intrinsic to the endpoint and lives
 * on {@see self::method()} rather than in config.
 */
enum Handler: string
{
    case Login = 'login';
    case LoginStore = 'login.store';
    case Register = 'register';
    case RegisterStore = 'register.store';
    case PasswordRequest = 'password.request';
    case PasswordEmail = 'password.email';
    case PasswordReset = 'password.reset';
    case PasswordUpdate = 'password.update';
    case Logout = 'logout';
    case PasswordConfirm = 'password.confirm';
    case PasswordConfirmStore = 'password.confirm.store';
    case PasswordConfirmation = 'password.confirmation';
    case VerificationNotice = 'verification.notice';
    case VerificationVerify = 'verification.verify';
    case VerificationSend = 'verification.send';
    case OidcJwks = 'oidc.jwks';
    case OidcDiscovery = 'oidc.discovery';
    case OidcUserinfo = 'oidc.userinfo';
    case OidcLogout = 'oidc.logout';
    case OidcIntrospect = 'oidc.introspect';
    case OidcRevoke = 'oidc.revoke';
    case PassportAuthorize = 'passport.authorizations.authorize';
    case PassportToken = 'passport.token';
    case PassportTokenRefresh = 'passport.token.refresh';
    case PassportApprove = 'passport.authorizations.approve';
    case PassportDeny = 'passport.authorizations.deny';

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
            self::Logout,
            self::PasswordConfirmStore,
            self::VerificationSend,
            self::OidcIntrospect,
            self::OidcRevoke,
            self::PassportToken,
            self::PassportTokenRefresh,
            self::PassportApprove => 'post',
            self::PassportDeny => 'delete',
            self::OidcUserinfo, self::OidcLogout => ['get', 'post'],
            default => 'get',
        };
    }
}
