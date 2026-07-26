<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Token;

/**
 * The per-issuer expectations a token validator asserts against. Injecting
 * this instead of reading the global config lets a validator serve a second
 * issuer without config juggling.
 */
final readonly class ValidatorConfig
{
    public function __construct(
        public string $issuer,
        public string $clientId,
        public int $leeway,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            issuer: (string) config('oidc-client.issuer'),
            clientId: (string) config('oidc-client.client_id'),
            leeway: (int) config('oidc-client.leeway', 60),
        );
    }
}
