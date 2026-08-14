<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client;

final readonly class ExchangedToken
{
    public function __construct(
        public string $accessToken,
        public int $expiresAt,
    ) {}

    public function expiresIn(): int
    {
        return max(0, $this->expiresAt - time());
    }
}
