<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Token;

use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use DateTimeInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Throwable;

/**
 * Shared machinery for validating provider-issued JWTs: parse, verify the
 * RS256 signature against the provider's JWKS, and assert the claims every
 * token type has in common. Concrete validators add their token-specific
 * claim checks on top.
 */
abstract class TokenValidator
{
    private readonly Parser $parser;

    private readonly Validator $signatureValidator;

    public function __construct(private readonly JwksKeyResolver $keys)
    {
        $this->parser = new Parser(new JoseEncoder);
        $this->signatureValidator = new Validator;
    }

    /**
     * The token name used in exception messages (e.g. "id_token").
     */
    abstract protected function tokenName(): string;

    protected function parseAndVerifySignature(string $jwt): UnencryptedToken
    {
        $name = $this->tokenName();

        try {
            $token = $this->parser->parse($jwt);
        } catch (Throwable $e) {
            throw new OidcClientException("The {$name} could not be parsed.", 0, $e);
        }

        if (! $token instanceof UnencryptedToken) {
            throw new OidcClientException("The {$name} is not a signed JWT.");
        }

        $this->assertHeaders($token);

        $kid = $token->headers()->get('kid');

        if (! is_string($kid)) {
            throw new OidcClientException("The {$name} has no kid header.");
        }

        $pem = $this->keys->publicKeyPem($kid);

        if (! $this->signatureValidator->validate($token, new SignedWith(new Sha256, InMemory::plainText($pem)))) {
            throw new OidcClientException("The {$name} signature is invalid.");
        }

        return $token;
    }

    /**
     * Token-specific header assertions, run before any JWKS lookup.
     */
    protected function assertHeaders(UnencryptedToken $token): void {}

    protected function assertIssuer(UnencryptedToken $token): void
    {
        $issuer = (string) config('oidc-client.issuer');

        if (rtrim((string) $token->claims()->get('iss'), '/') !== rtrim($issuer, '/')) {
            throw new OidcClientException("The {$this->tokenName()} issuer does not match.");
        }
    }

    /**
     * Assert the aud claim contains this client, returning the audience list.
     *
     * @return array<int, mixed>
     */
    protected function assertAudience(UnencryptedToken $token): array
    {
        $audience = (array) $token->claims()->get('aud', []);

        if (! in_array($this->clientId(), $audience, true)) {
            throw new OidcClientException("The {$this->tokenName()} audience does not include this client.");
        }

        return $audience;
    }

    protected function clientId(): string
    {
        return (string) config('oidc-client.client_id');
    }

    protected function leeway(): int
    {
        return (int) config('oidc-client.leeway', 60);
    }

    protected function timestamp(mixed $value, string $claim, bool $required = false): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if ($required) {
            throw new OidcClientException("The {$this->tokenName()} is missing or invalid {$claim} timestamp.");
        }

        if ($value !== null) {
            throw new OidcClientException("The {$this->tokenName()} {$claim} timestamp is invalid.");
        }

        return null;
    }
}
