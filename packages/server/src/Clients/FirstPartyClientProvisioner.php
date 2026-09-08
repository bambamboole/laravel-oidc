<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Audit\Auditor;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use SensitiveParameter;

final readonly class FirstPartyClientProvisioner
{
    private const ProvisioningKey = 'first-party';

    private const TokenExchangeGrant = 'urn:ietf:params:oauth:grant-type:token-exchange';

    public function __construct(
        private ClientRepository $clients,
        private Hasher $hasher,
        private Auditor $auditor,
        private ClientMetadataNormalizer $normalizer,
    ) {}

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
        #[SensitiveParameter] ?string $existingClientSecret = null,
    ): FirstPartyClientProvisioningResult {
        $name = trim($name);

        try {
            $redirectUris = $this->normalizer->uris($redirectUris, 'redirect URI');
            $postLogoutRedirectUris = $this->normalizer->uris($postLogoutRedirectUris, 'post-logout redirect URI');
            $allowedExchangeAudiences = $this->normalizer->audiences($allowedExchangeAudiences);
        } catch (ClientMetadataException $exception) {
            throw new FirstPartyClientProvisioningException($exception->getMessage(), previous: $exception);
        }

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
            return $this->recordProvisioned($this->transactionalProvision(
                $name,
                $redirectUris,
                $postLogoutRedirectUris,
                $allowedExchangeAudiences,
                $adoptClientId,
                $rotateSecret,
                $existingClientSecret,
            ));
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraint($exception)
                && Passport::client()->newQuery()->where('oidc_provisioning_key', self::ProvisioningKey)->exists()) {
                return $this->recordProvisioned($this->transactionalProvision(
                    $name,
                    $redirectUris,
                    $postLogoutRedirectUris,
                    $allowedExchangeAudiences,
                    $adoptClientId,
                    $rotateSecret,
                    $existingClientSecret,
                ));
            }

            throw $exception;
        }
    }

    private function recordProvisioned(FirstPartyClientProvisioningResult $result): FirstPartyClientProvisioningResult
    {
        $this->auditor->log(AuditEventType::ClientProvisioned, clientId: $result->clientId, context: [
            'created' => $result->wasCreated,
            'secret_rotated' => $result->secretRotated,
        ]);

        return $result;
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
        #[SensitiveParameter] ?string $existingClientSecret,
    ): FirstPartyClientProvisioningResult {
        $connection = config('passport.connection');

        return DB::connection(is_string($connection) ? $connection : null)->transaction(function () use (
            $name,
            $redirectUris,
            $postLogoutRedirectUris,
            $allowedExchangeAudiences,
            $adoptClientId,
            $rotateSecret,
            $existingClientSecret,
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

            if (! $created
                && $existingClientSecret !== null
                && ! $this->hasher->check($existingClientSecret, (string) $client->getRawOriginal('secret'))) {
                throw new FirstPartyClientProvisioningException('The existing first-party client secret does not match.');
            }

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

            $secret = $created ? $client->plainSecret : $existingClientSecret;

            if ($rotateSecret) {
                $this->clients->regenerateSecret($client);
                $secret = $client->plainSecret;
            }

            return new FirstPartyClientProvisioningResult(
                client: $client->refresh(),
                clientId: (string) $client->getKey(),
                clientSecret: $secret,
                wasCreated: $created,
                secretRotated: $rotateSecret,
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
}
