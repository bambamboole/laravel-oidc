<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowTwoFactorQrCodeController
{
    public function __construct(
        private readonly TwoFactorManager $twoFactor,
        private readonly TotpFactorProvider $totp,
    ) {}

    /**
     * @return JsonResponse|array<never, never>
     */
    public function __invoke(Request $request): JsonResponse|array
    {
        $user = $request->user((string) config('oidc.auth.guard', 'identity'));
        $factor = $this->twoFactor->currentFactor($user);

        if ($factor === null) {
            return [];
        }

        return new JsonResponse([
            'svg' => $this->totp->qrCodeSvg($factor, $user),
            'url' => $this->totp->qrCodeUrl($factor, $user),
        ]);
    }
}
