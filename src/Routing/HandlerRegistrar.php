<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Routing;

use Illuminate\Support\Facades\Route;

/**
 * Registers every enabled {@see Handler} as a route.
 *
 * The list of endpoints and their intrinsic HTTP verb come from the
 * {@see Handler} enum; each endpoint's configuration (path, controller,
 * middleware, or disabled) comes from {@see Handler::config()}.
 */
final class HandlerRegistrar
{
    public function register(): void
    {
        foreach (Handler::cases() as $handler) {
            $config = $handler->config();

            if ($config === false) {
                continue;
            }

            $method = $handler->method();

            Route::{$method}($config->route, $config->controller)
                ->name($handler->value)
                ->middleware($config->middleware);
        }
    }
}
