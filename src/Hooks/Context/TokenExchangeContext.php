<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks\Context;

use Bambamboole\LaravelOidc\Hooks\ClaimsBag;
use Illuminate\Contracts\Auth\Authenticatable;
use League\OAuth2\Server\Entities\ClientEntityInterface;

final readonly class TokenExchangeContext
{
    /**
     * @param  string[]  $grantedScopes
     * @param  array<string, mixed>  $subjectClaims
     */
    public function __construct(
        public Authenticatable $user,
        public ClientEntityInterface $client,
        public array $grantedScopes,
        public string $audience,
        public array $subjectClaims,
        public ClaimsBag $accessToken,
    ) {}
}
