<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthenticationMethods;
use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Auth\MultiFactor\FactorResponse;
use Bambamboole\LaravelOidc\Routing\Handler;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TwoFactorChallengeController
{
    public function __construct(
        private readonly AuthViewManager $views,
        private readonly FactorRegistry $factors,
        private readonly AuthenticationMethods $context,
    ) {}

    public function create(Request $request): mixed
    {
        if ($this->challengedUser($request) === null) {
            return redirect()->route(Handler::Login->value);
        }

        return $this->views->render(AuthViewManager::TwoFactorChallenge, $request);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = $this->challengedUser($request);

        if ($user === null) {
            return redirect()->route(Handler::Login->value);
        }

        $usesRecoveryCode = $request->filled('recovery_code');
        $providerKey = $usesRecoveryCode ? 'recovery_code' : (string) $request->session()->get('login.factor', 'totp');
        $provider = $this->factors->get($providerKey);
        $enrollment = $usesRecoveryCode
            ? $provider->enrollments($user)[0] ?? null
            : $this->pendingEnrollment($user, $providerKey, (string) $request->session()->get('login.factor_id'));

        if (! $enrollment instanceof FactorEnrollment) {
            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }

        $challenge = $provider->beginChallenge($user, $enrollment);
        $verification = $provider->verify($user, $challenge, new FactorResponse($request->only('code', 'recovery_code')));

        if (! $verification->verified) {
            $field = $usesRecoveryCode ? 'recovery_code' : 'code';

            throw ValidationException::withMessages([$field => __('The provided two factor authentication code was invalid.')]);
        }

        $this->context->add(...$verification->amr);

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget(['login.id', 'login.factor', 'login.factor_id']);
        $this->guard()->login($user, $remember);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
    }

    private function challengedUser(Request $request): ?Authenticatable
    {
        $id = $request->session()->get('login.id');

        return $id === null ? null : $this->guard()->getProvider()->retrieveById($id);
    }

    private function guard(): SessionGuard
    {
        $guard = Auth::guard((string) config('oidc.auth.guard', 'identity'));

        if (! $guard instanceof SessionGuard) {
            throw new RuntimeException('OIDC authentication requires a session guard.');
        }

        return $guard;
    }

    private function pendingEnrollment(Authenticatable $user, string $providerKey, string $id): ?FactorEnrollment
    {
        foreach ($this->factors->get($providerKey)->enrollments($user) as $enrollment) {
            if ($id === '' || $enrollment->id === $id) {
                return $enrollment;
            }
        }

        return null;
    }
}
