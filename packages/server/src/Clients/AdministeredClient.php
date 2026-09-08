<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Laravel\Passport\Client;

final readonly class AdministeredClient
{
    public function __construct(
        public Client $client,
        public ?string $plainSecret = null,
    ) {}
}
