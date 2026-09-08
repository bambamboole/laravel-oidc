<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Bambamboole\LaravelOidc\Server\Contracts\ScopeRepository;
use Bambamboole\LaravelOidc\Server\Scopes\AdminScope;
use Bambamboole\LaravelOidc\Server\Scopes\Scope;
use Illuminate\Validation\ValidationException;

/**
 * Semantic validation of a client definition. Errors are keyed by the JSON
 * field names of the administration API so they render as a Laravel 422
 * body without translation.
 */
final readonly class ClientDefinitionValidator
{
    public const array GrantTypes = [
        'authorization_code',
        'refresh_token',
        'client_credentials',
        ClientDefinition::TokenExchangeGrant,
    ];

    public function __construct(
        private ClientMetadataNormalizer $normalizer,
        private ScopeRepository $scopes,
    ) {}

    /**
     * @throws ValidationException
     */
    public function validate(ClientDefinition $definition, bool $confidential = true): ClientDefinition
    {
        $errors = [];
        $name = trim($definition->name);

        if ($name === '') {
            $errors['client_name'][] = 'The client name must not be empty.';
        } elseif (mb_strlen($name) > 255) {
            $errors['client_name'][] = 'The client name must not exceed 255 characters.';
        }

        $grantTypes = array_values(array_unique($definition->grantTypes));
        $redirectUris = $this->normalized($errors, 'redirect_uris', fn (): array => $this->normalizer->uris($definition->redirectUris, 'redirect URI'));
        $postLogoutRedirectUris = $this->normalized($errors, 'post_logout_redirect_uris', fn (): array => $this->normalizer->uris($definition->postLogoutRedirectUris, 'post-logout redirect URI'));
        $audiences = $this->normalized($errors, 'allowed_exchange_audiences', fn (): array => $this->normalizer->audiences($definition->allowedExchangeAudiences));
        $backchannelLogoutUri = $definition->backchannelLogoutUri === null
            ? null
            : $this->normalized($errors, 'backchannel_logout_uri', fn (): string => $this->normalizer->uri((string) $definition->backchannelLogoutUri, 'back-channel logout URI'));

        $this->validateGrantTypes($errors, $grantTypes, $redirectUris ?? [], $audiences ?? [], $confidential);
        $scopes = $this->validateScopes($errors, $definition->scopes, $grantTypes, $confidential);

        if ($definition->trusted && ! $confidential) {
            $errors['trusted'][] = 'A public client cannot skip consent.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new ClientDefinition(
            name: $name,
            redirectUris: $redirectUris ?? [],
            grantTypes: $grantTypes,
            scopes: $scopes,
            postLogoutRedirectUris: $postLogoutRedirectUris ?? [],
            backchannelLogoutUri: $backchannelLogoutUri,
            backchannelLogoutSessionRequired: $definition->backchannelLogoutSessionRequired,
            allowedExchangeAudiences: $audiences ?? [],
            trusted: $definition->trusted,
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<string>  $grantTypes
     * @param  list<string>  $redirectUris
     * @param  list<string>  $audiences
     */
    private function validateGrantTypes(array &$errors, array $grantTypes, array $redirectUris, array $audiences, bool $confidential): void
    {
        if ($grantTypes === []) {
            $errors['grant_types'][] = 'At least one grant type is required.';
        }

        foreach ($grantTypes as $grantType) {
            if (! in_array($grantType, self::GrantTypes, true)) {
                $errors['grant_types'][] = "The grant type [{$grantType}] is not supported.";
            }
        }

        $has = fn (string $grantType): bool => in_array($grantType, $grantTypes, true);

        if ($has('refresh_token') && ! $has('authorization_code')) {
            $errors['grant_types'][] = 'The refresh_token grant requires the authorization_code grant.';
        }

        if ($has('authorization_code') && $redirectUris === [] && ! isset($errors['redirect_uris'])) {
            $errors['redirect_uris'][] = 'The authorization_code grant requires at least one redirect URI.';
        }

        if ($has('client_credentials') && ! $confidential) {
            $errors['grant_types'][] = 'The client_credentials grant requires a confidential client.';
        }

        if ($has(ClientDefinition::TokenExchangeGrant) && $audiences === [] && ! isset($errors['allowed_exchange_audiences'])) {
            $errors['allowed_exchange_audiences'][] = 'The token exchange grant requires at least one allowed exchange audience.';
        }

        if (($has(ClientDefinition::TokenExchangeGrant) || $audiences !== []) && ! config('oidc.token_exchange.enabled', true)) {
            $errors['allowed_exchange_audiences'][] = 'Token exchange is disabled on this provider.';
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<string>|null  $scopes
     * @param  list<string>  $grantTypes
     * @return list<string>|null
     */
    private function validateScopes(array &$errors, ?array $scopes, array $grantTypes, bool $confidential): ?array
    {
        if ($scopes === null) {
            return null;
        }

        $scopes = array_values(array_unique($scopes));

        foreach ($scopes as $scope) {
            if ($scope === '*') {
                $errors['scopes'][] = 'The wildcard scope cannot be assigned to an administered client.';
            } elseif (! $this->scopes->find($scope) instanceof Scope) {
                $errors['scopes'][] = "The scope [{$scope}] is not defined.";
            }
        }

        if (in_array(AdminScope::id(), $scopes, true)) {
            if (! in_array('client_credentials', $grantTypes, true)) {
                $errors['scopes'][] = 'The administration scope requires the client_credentials grant.';
            }

            if (! $confidential) {
                $errors['scopes'][] = 'The administration scope requires a confidential client.';
            }
        }

        return $scopes;
    }

    /**
     * @template T
     *
     * @param  array<string, list<string>>  $errors
     * @param  callable(): T  $normalize
     * @return T|null
     */
    private function normalized(array &$errors, string $field, callable $normalize): mixed
    {
        try {
            return $normalize();
        } catch (ClientMetadataException $exception) {
            $errors[$field][] = $exception->getMessage();

            return null;
        }
    }
}
