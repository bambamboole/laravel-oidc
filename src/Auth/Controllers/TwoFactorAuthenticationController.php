<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorAuthenticationController
{
    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->twoFactor->enable($this->user($request), $request->boolean('force'));

        return $this->response($request, 'two-factor-authentication-enabled');
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $this->twoFactor->disable($this->user($request));

        return $this->response($request, 'two-factor-authentication-disabled');
    }

    private function response(Request $request, string $status): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', $status);
    }

    private function user(Request $request): Authenticatable
    {
        return $request->user((string) config('oidc.auth.guard', 'web'));
    }
}
