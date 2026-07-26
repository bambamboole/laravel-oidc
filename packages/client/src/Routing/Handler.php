<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Routing;

use Bambamboole\LaravelOidc\Client\Http\Controllers\BackchannelLogoutController;
use Bambamboole\LaravelOidc\Client\Http\Controllers\OidcCallbackController;
use Bambamboole\LaravelOidc\Client\Http\Controllers\OidcLoginController;
use Bambamboole\LaravelOidc\Client\Http\Controllers\OidcLogoutController;

/**
 * The canonical registry of every HTTP endpoint the relying party can register.
 *
 * Each case's value is the endpoint's route name and the key into the
 * `oidc-client.handlers` config. Route defaults live on {@see self::defaults()}
 * in code; config entries are sparse overrides — an absent entry means the
 * endpoint registers with its defaults (so package upgrades can add endpoints),
 * `false` disables it, and a partial entry overrides only the given keys. The
 * HTTP verb is intrinsic to the endpoint and lives on {@see self::method()}.
 *
 * The route names are the conventional Laravel ones (`login`, `logout`) so the
 * framework and the host app resolve them by name; the callback keeps the
 * `login.callback` name it has always had.
 */
enum Handler: string
{
    case Login = 'login';
    case Callback = 'login.callback';
    case Logout = 'logout';
    case BackchannelLogout = 'oidc.backchannel-logout';

    /**
     * Resolve this handler's configuration, or `false` when it is disabled.
     * The back-channel logout endpoint is additionally gated behind its
     * feature flag.
     */
    public function config(): HandlerConfig|false
    {
        if ($this === self::BackchannelLogout && ! config('oidc-client.backchannel_logout.enabled', false)) {
            return false;
        }

        /** @var array<string, array{route?: string, controller?: string|array{0: class-string, 1: string}, middleware?: array<int, string>}|false> $handlers */
        $handlers = config('oidc-client.handlers', []);
        $override = $handlers[$this->value] ?? null;

        if ($override === false) {
            return false;
        }

        $defaults = $this->defaults();
        $resolved = $override === null
            ? $defaults
            : new HandlerConfig(
                route: $override['route'] ?? $defaults->route,
                controller: $override['controller'] ?? $defaults->controller,
                middleware: $override['middleware'] ?? $defaults->middleware,
            );

        /** @var array<int, string> $globalMiddleware */
        $globalMiddleware = config('oidc-client.routes.middleware', []);
        $prefix = trim((string) config('oidc-client.routes.prefix', ''), '/');

        return new HandlerConfig(
            route: $prefix === '' ? $resolved->route : $prefix.'/'.ltrim($resolved->route, '/'),
            controller: $resolved->controller,
            middleware: [...$resolved->middleware, ...$globalMiddleware],
        );
    }

    /**
     * The complete package-owned route defaults for this handler.
     */
    public function defaults(): HandlerConfig
    {
        return match ($this) {
            self::Login => new HandlerConfig(
                route: 'login',
                controller: OidcLoginController::class,
                middleware: ['web'],
            ),
            self::Callback => new HandlerConfig(
                route: 'login/callback',
                controller: OidcCallbackController::class,
                middleware: ['web'],
            ),
            self::Logout => new HandlerConfig(
                route: 'logout',
                controller: OidcLogoutController::class,
                middleware: ['web'],
            ),
            self::BackchannelLogout => new HandlerConfig(
                route: 'oidc/backchannel-logout',
                controller: BackchannelLogoutController::class,
                middleware: ['throttle:60,1'],
            ),
        };
    }

    /**
     * The intrinsic HTTP verb for this endpoint.
     */
    public function method(): string
    {
        return match ($this) {
            self::Logout, self::BackchannelLogout => 'post',
            default => 'get',
        };
    }
}
