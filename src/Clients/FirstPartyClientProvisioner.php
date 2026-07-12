<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Clients;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

final readonly class FirstPartyClientProvisioner
{
    private const ProvisioningKey = 'first-party';

    private const TokenExchangeGrant = 'urn:ietf:params:oauth:grant-type:token-exchange';

    public function __construct(private ClientRepository $clients) {}

    /**
     * @param  string[]  $redirectUris
     * @param  string[]  $postLogoutRedirectUris
     * @param  string[]  $allowedExchangeAudiences
     */
    public function provision(
        string $name,
        array $redirectUris,
        array $postLogoutRedirectUris = [],
        array $allowedExchangeAudiences = [],
        ?string $adoptClientId = null,
        bool $rotateSecret = false,
    ): FirstPartyClientProvisioningResult {
        $name = trim($name);
        $redirectUris = $this->normalizeUris($redirectUris, 'redirect URI');
        $postLogoutRedirectUris = $this->normalizeUris($postLogoutRedirectUris, 'post-logout redirect URI');
        $allowedExchangeAudiences = $this->normalizeAudiences($allowedExchangeAudiences);

        if ($name === '') {
            throw new FirstPartyClientProvisioningException('The first-party client name must not be empty.');
        }

        if ($redirectUris === []) {
            throw new FirstPartyClientProvisioningException('At least one redirect URI is required.');
        }

        if ($allowedExchangeAudiences !== [] && ! config('oidc.token_exchange.enabled', true)) {
            throw new FirstPartyClientProvisioningException('Token exchange audiences cannot be configured while token exchange is disabled.');
        }

        try {
            return $this->transactionalProvision(
                $name,
                $redirectUris,
                $postLogoutRedirectUris,
                $allowedExchangeAudiences,
                $adoptClientId,
                $rotateSecret,
            );
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraint($exception)
                && Passport::client()->newQuery()->where('oidc_provisioning_key', self::ProvisioningKey)->exists()) {
                return $this->transactionalProvision(
                    $name,
                    $redirectUris,
                    $postLogoutRedirectUris,
                    $allowedExchangeAudiences,
                    $adoptClientId,
                    $rotateSecret,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  string[]  $redirectUris
     * @param  string[]  $postLogoutRedirectUris
     * @param  string[]  $allowedExchangeAudiences
     */
    private function transactionalProvision(
        string $name,
        array $redirectUris,
        array $postLogoutRedirectUris,
        array $allowedExchangeAudiences,
        ?string $adoptClientId,
        bool $rotateSecret,
    ): FirstPartyClientProvisioningResult {
        $connection = config('passport.connection');

        return DB::connection(is_string($connection) ? $connection : null)->transaction(function () use (
            $name,
            $redirectUris,
            $postLogoutRedirectUris,
            $allowedExchangeAudiences,
            $adoptClientId,
            $rotateSecret,
        ): FirstPartyClientProvisioningResult {
            $client = Passport::client()->newQuery()
                ->where('oidc_provisioning_key', self::ProvisioningKey)
                ->lockForUpdate()
                ->first();
            $created = false;

            if ($client !== null
                && $adoptClientId !== null
                && (string) $client->getKey() !== $adoptClientId) {
                throw new FirstPartyClientProvisioningException('A different client already owns the first-party provisioning key.');
            }

            if ($client === null && $adoptClientId !== null) {
                $client = Passport::client()->newQuery()->lockForUpdate()->find($adoptClientId);

                if ($client === null) {
                    throw new FirstPartyClientProvisioningException("The adoption client [{$adoptClientId}] does not exist.");
                }
            }

            if ($client === null) {
                $client = $this->clients->createAuthorizationCodeGrantClient($name, $redirectUris);
                $created = true;
            }

            $this->assertEligible($client);

            $grantTypes = ['authorization_code', 'refresh_token'];

            if ($allowedExchangeAudiences !== []) {
                $grantTypes[] = self::TokenExchangeGrant;
            }

            $client->forceFill([
                'name' => $name,
                'redirect_uris' => $redirectUris,
                'post_logout_redirect_uris' => json_encode($postLogoutRedirectUris, JSON_THROW_ON_ERROR),
                'allowed_exchange_audiences' => json_encode($allowedExchangeAudiences, JSON_THROW_ON_ERROR),
                'grant_types' => $grantTypes,
                'oidc_provisioning_key' => self::ProvisioningKey,
            ])->save();

            $outcome = $created
                ? FirstPartyClientProvisioningOutcome::Created
                : FirstPartyClientProvisioningOutcome::Reconciled;
            $secret = $created ? $client->plainSecret : null;

            if ($rotateSecret) {
                $this->clients->regenerateSecret($client);
                $outcome = FirstPartyClientProvisioningOutcome::Rotated;
                $secret = $client->plainSecret;
            }

            return new FirstPartyClientProvisioningResult(
                client: $client->refresh(),
                clientId: (string) $client->getKey(),
                clientSecret: $secret,
                outcome: $outcome,
            );
        });
    }

    private function assertEligible(Client $client): void
    {
        if ($client->getAttribute('revoked') === true) {
            throw new FirstPartyClientProvisioningException('The first-party client is revoked.');
        }

        if (! $client->confidential()) {
            throw new FirstPartyClientProvisioningException('The first-party client must be confidential.');
        }

        if (! $client->firstParty()) {
            throw new FirstPartyClientProvisioningException('The first-party client must not be owned by a user.');
        }
    }

    private function isUniqueConstraint(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return is_string($sqlState) && in_array($sqlState, ['23000', '23505'], true);
    }

    /**
     * @param  mixed[]  $values
     * @return string[]
     */
    private function normalizeUris(array $values, string $label): array
    {
        return $this->normalize($values, function (string $value) use ($label): void {
            $parts = parse_url($value);

            if (! is_array($parts)
                || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
                || ! isset($parts['host'])
                || isset($parts['user'])
                || isset($parts['fragment'])) {
                throw new FirstPartyClientProvisioningException("The {$label} [{$value}] must be an absolute HTTP(S) URI without user information or a fragment.");
            }
        });
    }

    /**
     * @param  mixed[]  $values
     * @return string[]
     */
    private function normalizeAudiences(array $values): array
    {
        return $this->normalize($values, function (string $value): void {
            $parts = parse_url($value);

            if (! is_array($parts) || ! isset($parts['scheme'])) {
                throw new FirstPartyClientProvisioningException("The audience [{$value}] must be an absolute URI identifier.");
            }
        });
    }

    /**
     * @param  mixed[]  $values
     * @param  callable(string): void  $validate
     * @return string[]
     */
    private function normalize(array $values, callable $validate): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new FirstPartyClientProvisioningException('Provisioning metadata values must be non-empty strings.');
            }

            $value = trim($value);
            $validate($value);
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }
}
