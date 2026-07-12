<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Pipeline;

use Illuminate\Support\Facades\Log;

class LoginApi
{
    private const array PROTECTED = [
        'iss', 'sub', 'aud', 'exp', 'iat', 'nbf', 'jti',
        'nonce', 'at_hash', 'c_hash', 'auth_time', 'azp', 'acr', 'amr',
    ];

    private bool $denied = false;

    private ?string $denyReason = null;

    private bool $mfaRequired = false;

    /** @var array<string, mixed> */
    private array $idTokenClaims = [];

    public function deny(string $reason): void
    {
        $this->denied = true;
        $this->denyReason = $reason;
    }

    public function requireMfa(): void
    {
        $this->mfaRequired = true;
    }

    public function setIdTokenClaim(string $name, mixed $value): void
    {
        if (in_array($name, self::PROTECTED, true)) {
            Log::warning("oidc: postLogin refused to set protected id_token claim [{$name}]");

            return;
        }

        $this->idTokenClaims[$name] = $value;
    }

    public function isDenied(): bool
    {
        return $this->denied;
    }

    public function denyReason(): ?string
    {
        return $this->denyReason;
    }

    public function mfaRequired(): bool
    {
        return $this->mfaRequired;
    }

    /** @return array<string, mixed> */
    public function idTokenClaims(): array
    {
        return $this->idTokenClaims;
    }
}
