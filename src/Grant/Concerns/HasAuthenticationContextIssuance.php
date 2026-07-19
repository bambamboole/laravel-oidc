<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Grant\Concerns;

use Bambamboole\LaravelOidc\Auth\AccessTokenContextLink;
use Bambamboole\LaravelOidc\Auth\Models\AuthenticationContext;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use DateInterval;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Shared with OidcAuthCodeGrant and OidcRefreshTokenGrant: after league mints + persists the access
 * token, decorate it with the context's access-token claims and record the token→context link. The
 * pending context is set per request by the grant and read-and-cleared here (Octane-safe).
 */
trait HasAuthenticationContextIssuance
{
    protected ?AuthenticationContext $pendingContext = null;

    /**
     * @param  list<ScopeEntityInterface>  $scopes
     */
    protected function issueAccessToken(
        DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        ?string $userIdentifier,
        array $scopes = []
    ): AccessTokenEntityInterface {
        $accessToken = parent::issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);

        $context = $this->pendingContext;
        $this->pendingContext = null;

        if ($context !== null && $accessToken instanceof OidcAccessToken) {
            foreach ($context->access_token_claims as $name => $value) {
                $accessToken->addExtraClaim((string) $name, $value);
            }

            app(AccessTokenContextLink::class)->link($accessToken->getIdentifier(), $context->id);
        }

        return $accessToken;
    }
}
