<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Clients;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use League\Uri\Http;
use League\Uri\Uri;
use League\Uri\Urn;
use Throwable;

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

            if ($this->containsForbiddenUriCharacters($value)
                || $this->hasMalformedPercentEscape($value)
                || filter_var($value, FILTER_VALIDATE_URL) === false
                || ! is_array($parts)
                || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || ! is_string($parts['host'] ?? null)
                || $parts['host'] === ''
                || isset($parts['user'])
                || isset($parts['pass'])
                || array_key_exists('fragment', $parts)) {
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
            if ($this->containsForbiddenUriCharacters($value)
                || $this->hasMalformedPercentEscape($value)
                || ! $this->isValidAudienceUri($value)) {
                throw new FirstPartyClientProvisioningException("The audience [{$value}] must be an absolute URI identifier.");
            }
        });
    }

    private function isValidAudienceUri(string $value): bool
    {
        try {
            $uri = Uri::new($value);
            $scheme = $uri->getScheme();

            if ($scheme === null) {
                return false;
            }

            if (in_array($scheme, ['http', 'https'], true)) {
                return Http::new($value)->getHost() !== '';
            }

            if ($scheme === 'urn') {
                Urn::new($value);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function containsForbiddenUriCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x20\x7F\\\\]/', $value) === 1;
    }

    private function hasMalformedPercentEscape(string $value): bool
    {
        return preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1;
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
