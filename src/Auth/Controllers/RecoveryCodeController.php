<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecoveryCodeController
{
    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    /**
     * @return JsonResponse|array<never, never>
     */
    public function index(Request $request): JsonResponse|array
    {
        $user = $request->user((string) config('oidc.auth.guard', 'web'));

        if ($this->twoFactor->currentFactor($user) === null) {
            return [];
        }

        return new JsonResponse($this->twoFactor->recoveryCodes($user));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->twoFactor->regenerateRecoveryCodes($request->user((string) config('oidc.auth.guard', 'web')));

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'recovery-codes-generated');
    }
}
