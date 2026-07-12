<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorSecretKeyController
{
    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function show(Request $request): JsonResponse
    {
        $factor = $this->twoFactor->currentFactor($request->user((string) config('oidc.auth.guard', 'web')));

        abort_if($factor === null, 404, 'Two factor authentication has not been enabled.');

        return new JsonResponse(['secretKey' => $factor->secret]);
    }
}
