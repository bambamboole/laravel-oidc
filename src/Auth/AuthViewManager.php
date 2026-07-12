<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;

class AuthViewManager
{
    public const string Register = 'register';

    public const string RequestPasswordResetLink = 'request-password-reset-link';

    public const string ResetPassword = 'reset-password';

    public const string VerifyEmail = 'verify-email';

    /**
     * @var array<string, Closure(Request): mixed>
     */
    private array $views = [];

    /**
     * @param  Closure(Request): mixed  $view
     */
    public function bind(string $name, Closure $view): void
    {
        $this->views[$name] = $view;
    }

    public function render(string $name, Request $request): mixed
    {
        $view = $this->views[$name] ?? null;

        if ($view === null) {
            throw new RuntimeException("No OIDC auth view has been configured for [{$name}].");
        }

        return $view($request);
    }
}
