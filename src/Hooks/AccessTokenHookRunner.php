<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks;

use Bambamboole\LaravelOidc\Hooks\Context\ClientCredentialsContext;
use Bambamboole\LaravelOidc\Hooks\Context\PostLoginContext;
use Bambamboole\LaravelOidc\Hooks\Context\RefreshContext;
use Bambamboole\LaravelOidc\Hooks\Context\TokenExchangeContext;
use Bambamboole\LaravelOidc\Support\ResolvesRequestGrantType;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use Bambamboole\LaravelOidc\Token\ResolvesTokenUser;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class AccessTokenHookRunner
{
    use ResolvesRequestGrantType;
    use ResolvesTokenUser;

    public function __construct(private readonly ClaimHooks $hooks) {}

    /** @return array<string, mixed> */
    public function claims(OidcAccessToken $token): array
    {
        $grantType = $token->getGrantType() ?? $this->requestGrantType();
        $bag = new ClaimsBag(Artifact::AccessToken);

        $scopes = array_map(
            fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $token->getScopes(),
        );
        $client = $token->getClient();
        $user = $this->resolveUser($token->getUserIdentifier() === null ? null : (string) $token->getUserIdentifier());

        [$trigger, $context] = match ($grantType) {
            'authorization_code' => $user === null ? [null, null] : [
                Trigger::PostLogin,
                new PostLoginContext($user, $client, $scopes, null, null, new ClaimsBag(Artifact::IdToken), $bag),
            ],
            'refresh_token' => $user === null ? [null, null] : [
                Trigger::Refresh,
                new RefreshContext($user, $client, $scopes, new ClaimsBag(Artifact::IdToken), $bag),
            ],
            'client_credentials' => [
                Trigger::ClientCredentials,
                new ClientCredentialsContext($client, $scopes, $bag),
            ],
            'urn:ietf:params:oauth:grant-type:token-exchange' => $user === null ? [null, null] : [
                Trigger::TokenExchange,
                new TokenExchangeContext($user, $client, $scopes, $token->exchangeAudience(), $token->subjectClaims(), $bag),
            ],
            default => [null, null],
        };

        if ($trigger === null) {
            return [];
        }

        $this->hooks->run($trigger, $context);

        return $bag->all();
    }
}
