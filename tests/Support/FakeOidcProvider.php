<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests\Support;

use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use phpseclib3\Crypt\RSA;

class FakeOidcProvider
{
    private readonly RSA\PrivateKey $privateKey;

    public function __construct()
    {
        /** @var RSA\PrivateKey $key */
        $key = RSA::createKey(2048);
        $this->privateKey = $key;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function rsaJwks(string $kid): array
    {
        /** @var RSA\PublicKey $public */
        $public = $this->privateKey->getPublicKey();
        $raw = $public->toString('Raw');

        return [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $this->base64Url($raw['n']->toBytes()),
            'e' => $this->base64Url($raw['e']->toBytes()),
        ]];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function idToken(array $claims, string $kid): string
    {
        $builder = new Builder(new JoseEncoder, ChainedFormatter::default());
        $builder = $builder->withHeader('kid', $kid);

        foreach ($claims as $name => $value) {
            $builder = match ($name) {
                'iss' => $builder->issuedBy((string) $value),
                'sub' => $builder->relatedTo((string) $value),
                'aud' => $builder->permittedFor(...(array) $value),
                'exp' => $builder->expiresAt($this->toDateTime($value)),
                'nbf' => $builder->canOnlyBeUsedAfter($this->toDateTime($value)),
                'iat' => $builder->issuedAt($this->toDateTime($value)),
                'jti' => $builder->identifiedBy((string) $value),
                default => $builder->withClaim($name, $value),
            };
        }

        $pem = (string) $this->privateKey->toString('PKCS8');

        return $builder->getToken(new Sha256, InMemory::plainText($pem))->toString();
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function rawIdToken(array $claims, string $kid): string
    {
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url((string) json_encode($claims, JSON_THROW_ON_ERROR));
        $encoded = $header.'.'.$payload;
        $signature = $this->privateKey
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PKCS1)
            ->sign($encoded);

        return $encoded.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function toDateTime(mixed $value): DateTimeImmutable
    {
        return (new DateTimeImmutable)->setTimestamp((int) $value);
    }
}
