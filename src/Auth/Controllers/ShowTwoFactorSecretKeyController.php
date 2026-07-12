<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowTwoFactorSecretKeyController
{
    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function __invoke(Request $request): JsonResponse
    {
        $factor = $this->twoFactor->currentFactor($request->user((string) config('oidc.auth.guard', 'web')));

        abort_if($factor === null, 404, 'Two factor authentication has not been enabled.');

        return new JsonResponse(['secretKey' => $factor->secret]);
    }
}
