<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Pipeline;

use Bambamboole\LaravelOidc\Auth\ProtocolClaims;
use Illuminate\Support\Facades\Log;

class LoginApi
{
    private bool $denied = false;

    private ?string $denyReason = null;

    private bool $mfaRequired = false;

    /** @var array<string, mixed> */
    private array $idTokenClaims = [];

    /** @var array<string, mixed> */
    private array $accessTokenClaims = [];

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
        if (ProtocolClaims::isReserved($name)) {
            Log::warning("oidc: postLogin refused to set protected id_token claim [{$name}]");

            return;
        }

        $this->idTokenClaims[$name] = $value;
    }

    public function setAccessTokenClaim(string $name, mixed $value): void
    {
        if (ProtocolClaims::isReserved($name)) {
            Log::warning("oidc: postLogin refused to set protected access_token claim [{$name}]");

            return;
        }

        $this->accessTokenClaims[$name] = $value;
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

    /** @return array<string, mixed> */
    public function accessTokenClaims(): array
    {
        return $this->accessTokenClaims;
    }
}
