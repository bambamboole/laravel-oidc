<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use Laravel\Passport\Client;

/**
 * The desired state of an administered client. `scopes` distinguishes an
 * unrestricted client (null, Passport's default) from one that may request no
 * scope at all (an empty list).
 */
final readonly class ClientDefinition
{
    public const string TokenExchangeGrant = 'urn:ietf:params:oauth:grant-type:token-exchange';

    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $grantTypes
     * @param  list<string>|null  $scopes
     * @param  list<string>  $postLogoutRedirectUris
     * @param  list<string>  $allowedExchangeAudiences
     */
    public function __construct(
        public string $name,
        public array $redirectUris = [],
        public array $grantTypes = ['authorization_code', 'refresh_token'],
        public ?array $scopes = null,
        public array $postLogoutRedirectUris = [],
        public ?string $backchannelLogoutUri = null,
        public bool $backchannelLogoutSessionRequired = false,
        public array $allowedExchangeAudiences = [],
        public bool $trusted = false,
    ) {}

    public static function fromClient(Client $client): self
    {
        $scopes = $client->getAttribute('scopes');
        $backchannelLogoutUri = $client->getAttribute('backchannel_logout_uri');

        return new self(
            name: (string) $client->getAttribute('name'),
            redirectUris: self::stringList($client->getAttribute('redirect_uris')),
            grantTypes: self::stringList($client->getAttribute('grant_types')),
            scopes: is_array($scopes) ? self::stringList($scopes) : null,
            postLogoutRedirectUris: self::stringList(json_decode((string) $client->getRawOriginal('post_logout_redirect_uris'), true)),
            backchannelLogoutUri: is_string($backchannelLogoutUri) && $backchannelLogoutUri !== '' ? $backchannelLogoutUri : null,
            backchannelLogoutSessionRequired: (bool) $client->getAttribute('backchannel_logout_session_required'),
            allowedExchangeAudiences: AllowedAudiences::of($client),
            trusted: (bool) $client->getAttribute('trusted'),
        );
    }

    /**
     * A copy with the given constructor arguments replaced, so a partial
     * update can be applied on top of the stored state.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    public function hasGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }

    /**
     * The API field names whose values differ between this definition and another.
     *
     * @return list<string>
     */
    public function changedFields(self $other): array
    {
        $fields = [
            'name' => 'client_name',
            'redirectUris' => 'redirect_uris',
            'grantTypes' => 'grant_types',
            'scopes' => 'scopes',
            'postLogoutRedirectUris' => 'post_logout_redirect_uris',
            'backchannelLogoutUri' => 'backchannel_logout_uri',
            'backchannelLogoutSessionRequired' => 'backchannel_logout_session_required',
            'allowedExchangeAudiences' => 'allowed_exchange_audiences',
            'trusted' => 'trusted',
        ];

        $changed = [];

        foreach ($fields as $property => $field) {
            if ($this->{$property} !== $other->{$property}) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /** @return list<string> */
    private static function stringList(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
