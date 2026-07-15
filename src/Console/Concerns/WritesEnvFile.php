<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Console\Concerns;

trait WritesEnvFile
{
    /**
     * @param  array<string, string>  $variables
     * @param  (callable(string): string)|null  $encoder
     */
    private function writeEnv(array $variables, ?callable $encoder = null): bool
    {
        $path = $this->laravel->environmentFilePath();
        $contents = @file_get_contents($path);

        if ($contents === false) {
            $this->error("Unable to read the environment file at [{$path}].");

            return false;
        }

        foreach ($variables as $name => $value) {
            $contents = $this->upsert($contents, $name, $encoder !== null ? $encoder($value) : $value);
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
}
