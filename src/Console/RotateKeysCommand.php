<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Console;

use Bambamboole\LaravelOidc\Token\Jwk;
use Bambamboole\LaravelOidc\Token\PassportKeys;
use Illuminate\Console\Command;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use Throwable;

class RotateKeysCommand extends Command
{
    protected $signature = 'oidc:rotate-keys {--print : Print the env variables instead of writing them to .env} {--force : Skip the confirmation prompt}';

    protected $description = 'Generate a new OIDC signing keypair as env variables, rolling the current public key into OIDC_PREVIOUS_PUBLIC_KEY';

    public function handle(): int
    {
        $current = $this->currentPublicKey();

        if (! $this->option('force') && ! $this->option('print') && ! $this->confirm('Generate a new signing keypair and write it to .env?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        /** @var PrivateKey $key */
        $key = RSA::createKey((int) config('oidc.key_size', 2048));
        $newPrivate = (string) $key;
        $newPublic = (string) $key->getPublicKey();

        $vars = [
            'PASSPORT_PRIVATE_KEY' => $newPrivate,
            'PASSPORT_PUBLIC_KEY' => $newPublic,
        ];

        if ($current !== null) {
            $vars['OIDC_PREVIOUS_PUBLIC_KEY'] = $current;
        }

        if ($this->option('print')) {
            $this->printVars($vars);
        } elseif (! $this->writeEnv($vars)) {
            return self::FAILURE;
        }

        $this->info('New signing key generated. New kid: '.Jwk::fromPem($newPublic)['kid']);

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
            return PassportKeys::publicKey();
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

    /** @param array<string, string> $vars */
    private function writeEnv(array $vars): bool
    {
        $path = $this->laravel->environmentFilePath();
        $contents = @file_get_contents($path);

        if ($contents === false) {
            $this->error("Unable to read the environment file at [{$path}].");

            return false;
        }

        foreach ($vars as $name => $pem) {
            $contents = $this->upsert($contents, $name, $this->encode($pem));
        }

        if (@file_put_contents($path, $contents) === false) {
            $this->error("Unable to write the environment file at [{$path}].");

            return false;
        }

        return true;
    }

    private function upsert(string $contents, string $name, string $value): string
    {
        $line = $name.'='.$value;
        $pattern = '/^'.preg_quote($name, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $line, $contents, 1);
        }

        return rtrim($contents, "\n")."\n".$line."\n";
    }

    private function encode(string $pem): string
    {
        return '"'.str_replace("\n", '\n', trim($pem)).'"';
    }
}
