<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use Laravel\Passport\Passport;
use RuntimeException;

final class PassportKeys
{
    public static function publicKey(): string
    {
        return self::key('public');
    }

    public static function privateKey(): string
    {
        return self::key('private');
    }

    private static function key(string $type): string
    {
        $key = str_replace('\n', "\n", (string) config("passport.{$type}_key"));

        if ($key !== '') {
            return $key;
        }

        $path = Passport::keyPath("oauth-{$type}.key");
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException(
                "Unable to read the OAuth {$type} key from [{$path}]. Run `php artisan passport:keys` or set PASSPORT_".strtoupper($type).'_KEY.',
            );
        }

        return $contents;
    }
}
