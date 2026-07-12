<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Clients;

use Laravel\Passport\Client;

final readonly class FirstPartyClientProvisioningResult
{
    public function __construct(
        public Client $client,
        public string $clientId,
        public ?string $clientSecret,
        public FirstPartyClientProvisioningOutcome $outcome,
    ) {}
}
