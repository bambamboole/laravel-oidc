<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Laravel\Passport\Client;

final readonly class AdminClientProvisioningResult
{
    public function __construct(
        public Client $client,
        public string $clientId,
        public ?string $clientSecret,
        public bool $wasCreated,
        public bool $secretRotated,
    ) {}
}
