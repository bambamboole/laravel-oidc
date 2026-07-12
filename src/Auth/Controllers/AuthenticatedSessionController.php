<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::Login, $request);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $username = (string) config('oidc.auth.username', 'email');

        $request->validate([
            $username => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            $username => $request->string($username)->lower()->value(),
            'password' => $request->string('password')->value(),
        ];

        if (! Auth::guard($this->guard())->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([$username => __('auth.failed')]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard($this->guard())->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect('/');
    }

    private function guard(): string
    {
        return (string) config('oidc.auth.guard', 'web');
    }
}
