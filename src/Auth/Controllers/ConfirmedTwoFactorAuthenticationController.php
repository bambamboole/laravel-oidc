<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfirmedTwoFactorAuthenticationController
{
    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        if (! $this->twoFactor->confirm($request->user((string) config('oidc.auth.guard', 'web')), $request->string('code')->value())) {
            throw ValidationException::withMessages([
                'code' => __('The provided two factor authentication code was invalid.'),
            ])->errorBag('confirmTwoFactorAuthentication');
        }

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', 'two-factor-authentication-confirmed');
    }
}
