<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

use RuntimeException;

/**
 * A client administration request that conflicts with the client's current
 * state. `reason` is a stable machine-readable code the HTTP layer maps to a
 * 409 response.
 */
final class ClientAdministrationException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function managedClient(): self
    {
        return new self('managed_client', 'This client is managed by an artisan command and cannot be modified through the API.');
    }

    public static function selfRevocation(): self
    {
        return new self('self_revocation', 'A client cannot revoke itself.');
    }

    public static function publicClient(): self
    {
        return new self('public_client', 'A public client has no secret to rotate.');
    }

    public static function revoked(): self
    {
        return new self('revoked', 'The client has been revoked.');
    }

    public static function administrationDisabled(): self
    {
        return new self('admin_disabled', 'Client administration is disabled; set oidc.admin.enabled to true.');
    }
}
