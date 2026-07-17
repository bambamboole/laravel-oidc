<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Support;

use Bambamboole\LaravelOidc\Contracts\EnvironmentStore;

final class EnvironmentFile implements EnvironmentStore
{
    public function __construct(private readonly ?string $path = null) {}

    public function write(array $variables, ?callable $encoder = null): void
    {
        $path = $this->path ?? app()->environmentFilePath();
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new EnvironmentWriteException("Unable to read the environment file at [{$path}].");
        }

        foreach ($variables as $name => $value) {
            $contents = $this->upsert($contents, $name, $encoder !== null ? $encoder($value) : $value);
        }

        $temp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if (@file_put_contents($temp, $contents, LOCK_EX) === false) {
            @unlink($temp);

            throw new EnvironmentWriteException("Unable to write the environment file at [{$path}].");
        }

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new EnvironmentWriteException("Unable to write the environment file at [{$path}].");
        }
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
