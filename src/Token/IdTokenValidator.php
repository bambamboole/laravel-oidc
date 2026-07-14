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

class IdTokenValidator
{
    public function __construct(private readonly JwksKeyResolver $keys) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(string $idToken, string $expectedNonce): array
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($idToken);
        } catch (Throwable $e) {
            throw new OidcClientException('The id_token could not be parsed.', 0, $e);
        }

        if (! $token instanceof UnencryptedToken) {
            throw new OidcClientException('The id_token is not a signed JWT.');
        }

        $kid = $token->headers()->get('kid');

        if (! is_string($kid)) {
            throw new OidcClientException('The id_token has no kid header.');
        }

        $pem = $this->keys->publicKeyPem($kid);

        if (! (new Validator)->validate($token, new SignedWith(new Sha256, InMemory::plainText($pem)))) {
            throw new OidcClientException('The id_token signature is invalid.');
        }

        $this->assertClaims($token, $expectedNonce);

        return $token->claims()->all();
    }

    private function assertClaims(UnencryptedToken $token, string $expectedNonce): void
    {
        $claims = $token->claims();
        $leeway = (int) config('oidc-client.leeway', 60);
        $now = time();

        $issuer = (string) config('oidc-client.issuer');
        if (rtrim((string) $claims->get('iss'), '/') !== rtrim($issuer, '/')) {
            throw new OidcClientException('The id_token issuer does not match.');
        }

        $sub = $claims->get('sub');
        if (! is_string($sub) || $sub === '') {
            throw new OidcClientException('The id_token is missing a subject.');
        }

        $clientId = (string) config('oidc-client.client_id');
        $audience = (array) $claims->get('aud', []);
        if (! in_array($clientId, $audience, true)) {
            throw new OidcClientException('The id_token audience does not include this client.');
        }

        $azp = $claims->get('azp');
        if (count($audience) > 1) {
            if (! is_string($azp) || $azp !== $clientId) {
                throw new OidcClientException('The id_token azp does not match this client.');
            }
        } elseif (is_string($azp) && $azp !== $clientId) {
            throw new OidcClientException('The id_token azp does not match this client.');
        }

        if ($claims->get('nonce') !== $expectedNonce) {
            throw new OidcClientException('The id_token nonce does not match.');
        }

        $exp = $this->timestamp($claims->get('exp'), 'exp', true);
        if ($now > $exp + $leeway) {
            throw new OidcClientException('The id_token has expired.');
        }

        $nbf = $this->timestamp($claims->get('nbf'), 'nbf');
        if ($nbf !== null && $now + $leeway < $nbf) {
            throw new OidcClientException('The id_token is not yet valid.');
        }

        $iat = $this->timestamp($claims->get('iat'), 'iat', true);
        if ($now + $leeway < $iat) {
            throw new OidcClientException('The id_token was issued in the future.');
        }
    }

    private function timestamp(mixed $value, string $claim, bool $required = false): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if ($required) {
            throw new OidcClientException("The id_token is missing or invalid {$claim} timestamp.");
        }

        if ($value !== null) {
            throw new OidcClientException("The id_token {$claim} timestamp is invalid.");
        }

        return null;
    }
}
