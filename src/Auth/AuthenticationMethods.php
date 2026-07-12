<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

final class AuthenticationMethods
{
    public const string SESSION_KEY = 'oidc.amr';

    public function start(string ...$methods): void
    {
        session()->put(self::SESSION_KEY, $this->dedupe($methods));
    }

    public function add(string ...$methods): void
    {
        $existing = (array) session()->get(self::SESSION_KEY, []);

        session()->put(self::SESSION_KEY, $this->dedupe([...$existing, ...$methods]));
    }

    /**
     * @param  list<string>  $amr
     */
    public static function deriveAcr(array $amr): ?string
    {
        if ($amr === []) {
            return null;
        }

        return count($amr) > 1 ? '2' : '1';
    }

    /**
     * @param  array<int, mixed>  $methods
     * @return list<string>
     */
    private function dedupe(array $methods): array
    {
        return array_values(array_unique(array_filter($methods, is_string(...))));
    }
}
