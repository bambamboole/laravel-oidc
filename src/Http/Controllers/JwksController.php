<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Token\Jwk;
use Bambamboole\LaravelOidc\Token\PassportKeys;
use Illuminate\Http\JsonResponse;

class JwksController
{
    public function __invoke(): JsonResponse
    {
        $keys = [Jwk::fromPem(PassportKeys::publicKey())];

        foreach (config('oidc.additional_public_keys', []) as $pem) {
            $keys[] = Jwk::fromPem($pem);
        }

        return response()
            ->json(['keys' => $keys])
            ->header('Cache-Control', 'max-age=3600, public');
    }
}
