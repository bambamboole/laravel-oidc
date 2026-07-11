<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks;

use Illuminate\Support\Facades\Log;

final class ClaimsBag
{
    private const array COMMON = ['iss', 'sub', 'aud', 'exp', 'iat', 'nbf', 'jti'];

    private const array ID_TOKEN = ['nonce', 'at_hash', 'c_hash', 'auth_time', 'azp', 'acr', 'amr'];

    private const array ACCESS_TOKEN = ['client_id', 'scope', 'scopes', 'cnf', 'act'];

    /** @var array<string, mixed> */
    private array $claims = [];

    public function __construct(private readonly Artifact $artifact) {}

    public function set(string $name, mixed $value): static
    {
        if ($this->isProtected($name)) {
            Log::warning("oidc: refused to override protected claim [{$name}] in {$this->artifact->name}");

            return $this;
        }

        $this->claims[$name] = $value;

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->claims);
    }

    public function forget(string $name): static
    {
        unset($this->claims[$name]);

        return $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->claims;
    }

    private function isProtected(string $name): bool
    {
        $blocked = match ($this->artifact) {
            Artifact::IdToken => [...self::COMMON, ...self::ID_TOKEN],
            Artifact::AccessToken => [...self::COMMON, ...self::ACCESS_TOKEN],
            Artifact::Userinfo => self::COMMON,
        };

        return in_array($name, $blocked, true);
    }
}
