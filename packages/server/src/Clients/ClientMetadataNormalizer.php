<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Clients;

/**
 * Trims, validates, and deduplicates the URI and audience lists stored on a
 * client. Shared by the first-party provisioner and the administration API so
 * both persist identical canonical values.
 */
final class ClientMetadataNormalizer
{
    /**
     * @param  mixed[]  $values
     * @return list<string>
     */
    public function uris(array $values, string $label): array
    {
        return $this->normalize($values, fn (string $value) => $this->assertHttpUri($value, $label));
    }

    public function uri(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new ClientMetadataException("The {$label} must not be empty.");
        }

        $this->assertHttpUri($value, $label);

        return $value;
    }

    /**
     * @param  mixed[]  $values
     * @return list<string>
     */
    public function audiences(array $values): array
    {
        return $this->normalize($values, function (string $value): void {
            if (! str_starts_with(strtolower($value), 'urn:') && ! $this->isHttpUrl($value)) {
                throw new ClientMetadataException("The audience [{$value}] must be an HTTP(S) URL or a urn: identifier.");
            }
        });
    }

    private function assertHttpUri(string $value, string $label): void
    {
        $parts = parse_url($value);

        if (preg_match('/[\x00-\x20\x7F\\\\]|%(?![0-9A-Fa-f]{2})/', $value) === 1
            || filter_var($value, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! is_string($parts['host'] ?? null)
            || $parts['host'] === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || array_key_exists('fragment', $parts)) {
            throw new ClientMetadataException("The {$label} [{$value}] must be an absolute HTTP(S) URI without user information or a fragment.");
        }
    }

    private function isHttpUrl(string $value): bool
    {
        $parts = parse_url($value);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== '';
    }

    /**
     * @param  mixed[]  $values
     * @param  callable(string): void  $validate
     * @return list<string>
     */
    private function normalize(array $values, callable $validate): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new ClientMetadataException('Provisioning metadata values must be non-empty strings.');
            }

            $value = trim($value);
            $validate($value);
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }
}
