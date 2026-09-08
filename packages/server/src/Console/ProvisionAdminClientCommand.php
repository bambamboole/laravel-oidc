<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Console;

use Bambamboole\LaravelOidc\Server\Clients\AdminClientProvisioner;
use Bambamboole\LaravelOidc\Server\Clients\ClientAdministrationException;
use Illuminate\Console\Command;

class ProvisionAdminClientCommand extends Command
{
    protected $signature = 'oidc:admin-client
        {--name=OIDC Administration : Client display name}
        {--rotate : Rotate the admin client secret}';

    protected $description = 'Provision the package-managed client that administers OAuth clients over the API';

    public function __construct(private readonly AdminClientProvisioner $provisioner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->option('name');

        try {
            $result = $this->provisioner->provision(
                name: is_string($name) ? $name : 'OIDC Administration',
                rotateSecret: (bool) $this->option('rotate'),
            );
        } catch (ClientAdministrationException $exception) {
            $this->error($exception->getMessage());

            return $exception->reason === 'admin_disabled' ? self::INVALID : self::FAILURE;
        }

        $this->line('OIDC_ADMIN_CLIENT_ID='.$result->clientId);

        if ($result->clientSecret !== null) {
            $this->line('OIDC_ADMIN_CLIENT_SECRET='.$result->clientSecret);

            return self::SUCCESS;
        }

        $this->info('The admin client already exists; pass --rotate to issue a new secret.');

        return self::SUCCESS;
    }
}
