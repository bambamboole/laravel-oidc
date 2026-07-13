<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use Bambamboole\LaravelOidc\Auth\AuthenticationMethods;
use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Bambamboole\LaravelOidc\Hooks\Artifact;
use Bambamboole\LaravelOidc\Hooks\ClaimHooks;
use Bambamboole\LaravelOidc\Hooks\ClaimsBag;
use Bambamboole\LaravelOidc\Hooks\Context\PostLoginContext;
use Bambamboole\LaravelOidc\Hooks\Context\RefreshContext;
use Bambamboole\LaravelOidc\Hooks\Trigger;
use Bambamboole\LaravelOidc\Issuer;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use RuntimeException;

class IdTokenBuilder
{
    use ResolvesTokenUser;

    public function __construct(
        private readonly ClaimsResolver $claims,
        private readonly ClaimHooks $hooks,
    ) {}

    /**
     * @param  array<int, string>  $amr
     * @param  array<string, mixed>  $idTokenClaims
     */
    public function build(AccessTokenEntityInterface $accessToken, ?string $nonce, ?int $authTime, ?string $grantType = null, array $amr = [], array $idTokenClaims = []): string
    {
        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText(PassportKeys::privateKey()),
            InMemory::plainText(PassportKeys::publicKey()),
        );

        $clientId = $accessToken->getClient()->getIdentifier();
        $scopes = array_map(
            fn (ScopeEntityInterface $scope) => $scope->getIdentifier(),
            $accessToken->getScopes(),
        );
        $now = new DateTimeImmutable;

        $builder = $config->builder()
            ->withHeader('kid', Jwk::fromPem(PassportKeys::publicKey())['kid'])
            ->issuedBy(Issuer::url())
            ->permittedFor($clientId)
            ->relatedTo((string) $accessToken->getUserIdentifier())
            ->issuedAt($now)
            ->expiresAt($now->modify('+'.config('oidc.token_lifetimes.id_token').' seconds'))
            ->withClaim('azp', $clientId)
            ->withClaim('at_hash', $this->atHash($accessToken->toString()));

        if ($nonce !== null && $nonce !== '') {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        if ($authTime !== null) {
            $builder = $builder->withClaim('auth_time', $authTime);
        }

        if ($amr !== []) {
            $amr = array_values($amr);
            $builder = $builder->withClaim('amr', $amr);

            $acr = AuthenticationMethods::deriveAcr($amr);
            if ($acr !== null) {
                $builder = $builder->withClaim('acr', $acr);
            }
        }

        foreach ($idTokenClaims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $user = $this->resolveUser((string) $accessToken->getUserIdentifier())
            ?? throw new RuntimeException(
                'Unable to resolve the user for id_token issuance: '.$accessToken->getUserIdentifier(),
            );

        foreach ($this->claims->resolve($user)->forScopes($scopes) as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $trigger = match ($grantType) {
            'authorization_code' => Trigger::PostLogin,
            'refresh_token' => Trigger::Refresh,
            default => null,
        };

        if ($trigger !== null) {
            $bag = new ClaimsBag(Artifact::IdToken);
            $context = $trigger === Trigger::PostLogin
                ? new PostLoginContext($user, $accessToken->getClient(), $scopes, $nonce, $authTime, $bag, new ClaimsBag(Artifact::AccessToken))
                : new RefreshContext($user, $accessToken->getClient(), $scopes, $bag, new ClaimsBag(Artifact::AccessToken));
            $this->hooks->run($trigger, $context);

            foreach ($bag->all() as $name => $value) {
                $builder = $builder->withClaim($name, $value);
            }
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    private function atHash(string $accessTokenJwt): string
    {
        $hash = substr(hash('sha256', $accessTokenJwt, true), 0, 16);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
}
