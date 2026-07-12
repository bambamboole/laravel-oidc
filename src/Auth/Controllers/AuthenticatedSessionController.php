<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Auth\MultiFactor\FactorRegistry;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthenticatedSessionController
{
    public function __construct(
        private readonly AuthViewManager $views,
        private readonly FactorRegistry $factors,
    ) {}

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

        $guard = Auth::guard($this->guard());

        if (! $guard instanceof SessionGuard) {
            throw new RuntimeException('OIDC authentication requires a session guard.');
        }

        $provider = $guard->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages([$username => __('auth.failed')]);
        }

        if (config('hashing.rehash_on_login', true)) {
            $provider->rehashPasswordIfRequired($user, $credentials);
        }

        $enrollments = $this->factors->challengeableEnrollments($user);

        if ($enrollments !== []) {
            $request->session()->put([
                'login.id' => $user->getAuthIdentifier(),
                'login.remember' => $request->boolean('remember'),
                'login.factor' => $enrollments[0]->providerKey,
                'login.factor_id' => $enrollments[0]->id,
            ]);

            if ($request->wantsJson()) {
                return new JsonResponse(['two_factor' => true]);
            }

            return redirect()->route('two-factor.login');
        }

        $guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
    }

    private function guard(): string
    {
        return (string) config('oidc.auth.guard', 'web');
    }
}
