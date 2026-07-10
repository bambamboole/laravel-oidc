<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks\Context;

use Bambamboole\LaravelOidc\Hooks\ClaimsBag;
use League\OAuth2\Server\Entities\ClientEntityInterface;

final readonly class ClientCredentialsContext
{
    /** @param string[] $grantedScopes */
    public function __construct(
        public ClientEntityInterface $client,
        public array $grantedScopes,
        public ClaimsBag $accessToken,
    ) {}
}
