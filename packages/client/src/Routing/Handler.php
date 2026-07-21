<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Routing;

/**
 * The canonical registry of every HTTP endpoint the relying party can register.
 *
 * Each case's value is the endpoint's route name and the key into the
 * `oidc-client.handlers` config. The HTTP verb is intrinsic to the endpoint and
 * lives on {@see self::method()} rather than in config, so a consumer can swap
 * the path, controller, or middleware — or disable an endpoint — without being
 * able to break it with a mis-set verb.
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
     * Resolve this handler's configuration, or `false` when it is disabled (or
     * absent from config). The back-channel logout endpoint is additionally
     * gated behind its feature flag.
     */
    public function config(): HandlerConfig|false
    {
        if ($this === self::BackchannelLogout && ! config('oidc-client.backchannel_logout.enabled', false)) {
            return false;
        }

        /** @var array{route: string, controller: string|array{0: class-string, 1: string}, middleware?: array<int, string>}|false $config */
        $config = config('oidc-client.handlers', [])[$this->value] ?? false;

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
