<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnableTwoFactorAuthenticationController
{
    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $this->twoFactor->enable(
            $request->user((string) config('oidc.auth.guard', 'identity')),
            $request->boolean('force'),
        );

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'two-factor-authentication-enabled');
    }
}
