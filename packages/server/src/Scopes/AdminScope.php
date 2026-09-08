<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Scopes;

use Laravel\Passport\Client;

/**
 * The scope that unlocks the client administration API. It is only ever
 * issued through the client_credentials grant to a confidential, owner-less
 * client whose `scopes` column lists it explicitly: a null (unrestricted)
 * column and the `*` wildcard never qualify, so no pre-existing client gains
 * administrative access by enabling the feature.
 */
final class AdminScope
{
    public const string DefaultId = 'oidc:admin';

    public static function id(): string
    {
        $configured = config('oidc.admin.scope');

        return is_string($configured) && $configured !== '' ? $configured : self::DefaultId;
    }

    public static function is(string $scope): bool
    {
        return $scope === self::id();
    }

    public static function isListedOn(Client $client): bool
    {
        $scopes = $client->getAttribute('scopes');

        return is_array($scopes) && in_array(self::id(), $scopes, true);
    }

    public static function issuableTo(string $grantType, ?Client $client): bool
    {
        return $grantType === 'client_credentials'
            && $client !== null
            && $client->confidential()
            && $client->firstParty()
            && self::isListedOn($client);
    }
}
