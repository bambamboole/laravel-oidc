<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use Bambamboole\LaravelOidcClient\Routing\Handler;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class OidcClientManager
{
    /**
     * @var (Closure(string, array<string, mixed>): (Authenticatable|null))|null
     */
    private ?Closure $resolveUsersUsing = null;

    /**
     * Register every enabled {@see Handler} as a route.
     *
     * The list of endpoints and their intrinsic HTTP verb come from the
     * {@see Handler} enum; each endpoint's path, controller, and middleware
     * (or whether it is disabled) come from `oidc-client.handlers`.
     */
    public function routes(): void
    {
        foreach (Handler::cases() as $handler) {
            $config = $handler->config();

            if ($config === false) {
                continue;
            }

            Route::{$handler->method()}($config->route, $config->controller)
                ->name($handler->value)
                ->middleware($config->middleware);
        }
    }

    /**
     * @param  Closure(string, array<string, mixed>): (Authenticatable|null)  $callback
     */
    public function resolveUsersUsing(Closure $callback): void
    {
        $this->resolveUsersUsing = $callback;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function resolveUser(string $sub, array $claims): ?Authenticatable
    {
        if ($this->resolveUsersUsing !== null) {
            return ($this->resolveUsersUsing)($sub, $claims);
        }

        $guard = (string) config('oidc-client.login_guard', 'web');

        $provider = Auth::createUserProvider(config("auth.guards.{$guard}.provider"));

        return $provider?->retrieveById($sub);
    }
}
