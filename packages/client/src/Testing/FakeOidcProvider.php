<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Testing;

use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Ecdsa\Sha256 as EcdsaSha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\RSA;

class FakeOidcProvider
{
    private readonly RSA\PrivateKey $privateKey;

    private ?EC\PrivateKey $ecPrivateKey = null;

    public function __construct()
    {
        /** @var RSA\PrivateKey $key */
        $key = RSA::createKey(2048);
        $this->privateKey = $key;
    }

    private function ecPrivateKey(): EC\PrivateKey
    {
        if ($this->ecPrivateKey === null) {
            /** @var EC\PrivateKey $key */
            $key = EC::createKey('secp256r1');
            $this->ecPrivateKey = $key;
        }

        return $this->ecPrivateKey;
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
     * @return array<int, array<string, string>>
     */
    public function ecJwks(string $kid): array
    {
        /** @var array{keys?: array<int, array<string, string>>}|array<string, string> $jwk */
        $jwk = json_decode((string) $this->ecPrivateKey()->getPublicKey()->toString('JWK'), true);
        /** @var array<string, string> $key */
        $key = $jwk['keys'][0] ?? $jwk;

        return [array_merge($key, [
            'use' => 'sig',
            'alg' => 'ES256',
            'kid' => $kid,
        ])];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function idToken(array $claims, string $kid, string $algorithm = 'RS256'): string
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

        [$signer, $pem] = $this->signerFor($algorithm);

        return $builder->getToken($signer, InMemory::plainText($pem))->toString();
    }

    /**
     * @return array{0: Signer, 1: string}
     */
    private function signerFor(string $algorithm): array
    {
        return match ($algorithm) {
            'RS256' => [new Sha256, (string) $this->privateKey->toString('PKCS8')],
            'ES256' => [new EcdsaSha256, (string) $this->ecPrivateKey()->toString('PKCS8')],
            default => throw new InvalidArgumentException("The fake provider cannot sign with [{$algorithm}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function logoutToken(array $claims, string $kid): string
    {
        $builder = (new Builder(new JoseEncoder, ChainedFormatter::default()))
            ->withHeader('kid', $kid)
            ->withHeader('typ', 'logout+jwt');

        foreach ($claims as $name => $value) {
            $builder = match ($name) {
                'iss' => $builder->issuedBy((string) $value),
                'sub' => $builder->relatedTo((string) $value),
                'aud' => $builder->permittedFor(...(array) $value),
                'exp' => $builder->expiresAt($this->toDateTime($value)),
                'iat' => $builder->issuedAt($this->toDateTime($value)),
                'jti' => $builder->identifiedBy((string) $value),
                default => $builder->withClaim($name, $value),
            };
        }

        return $builder->getToken(new Sha256, InMemory::plainText((string) $this->privateKey->toString('PKCS8')))->toString();
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
