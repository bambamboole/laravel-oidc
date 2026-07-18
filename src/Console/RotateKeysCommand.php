<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Console;

use Bambamboole\LaravelOidc\Console\Concerns\WritesEnvFile;
use Bambamboole\LaravelOidc\Token\SigningKeyGenerator;
use Bambamboole\LaravelOidc\Token\SigningKeys;
use Illuminate\Console\Command;
use Throwable;

class RotateKeysCommand extends Command
{
    use WritesEnvFile;

    protected $signature = 'oidc:rotate-keys {--print : Print the env variables instead of writing them to .env} {--force : Skip the confirmation prompt}';

    protected $description = 'Generate a new OIDC signing keypair as env variables, rolling the current public key into OIDC_PREVIOUS_PUBLIC_KEY';

    public function __construct(private readonly SigningKeyGenerator $keys)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $current = $this->currentPublicKey();

        if (! $this->option('force') && ! $this->option('print') && ! $this->confirm('Generate a new signing keypair and write it to .env?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $generated = $this->keys->generate();

        $vars = [
            'OIDC_PRIVATE_KEY' => $generated->privateKeyPem,
            'OIDC_PUBLIC_KEY' => $generated->publicKeyPem,
        ];

        if ($current !== null) {
            $vars['OIDC_PREVIOUS_PUBLIC_KEY'] = $current;
        }

        if ($this->option('print')) {
            $this->printVars($vars);
        } elseif (! $this->writeEnv($vars, $this->encode(...))) {
            return self::FAILURE;
        }

        $this->info('New signing key generated. New kid: '.$generated->kid);

        if ($current !== null) {
            $this->line('The previous public key stays in JWKS via OIDC_PREVIOUS_PUBLIC_KEY. Remove it once every token signed by it has expired.');
        }

        if (! $this->option('print')) {
            $this->warn('Restart the app (and any queue workers) so the new keys take effect.');
        }

        return self::SUCCESS;
    }

    private function currentPublicKey(): ?string
    {
        try {
            return SigningKeys::publicKey();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, string> $vars */
    private function printVars(array $vars): void
    {
        $this->warn('Add these to your environment (the private key is secret — never commit it):');

        foreach ($vars as $name => $pem) {
            $this->line($name.'='.$this->encode($pem));
        }
    }

    private function encode(string $pem): string
    {
        return '"'.str_replace("\n", '\n', trim($pem)).'"';
    }
}
