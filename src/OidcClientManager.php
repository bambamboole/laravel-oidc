<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class OidcClientManager
{
    /**
     * @var (Closure(string, array<string, mixed>): (Authenticatable|null))|null
     */
    private ?Closure $resolveUsersUsing = null;

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
