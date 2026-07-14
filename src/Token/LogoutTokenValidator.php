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

class LogoutTokenValidator
{
    private const string EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    public function __construct(private readonly JwksKeyResolver $keys) {}

    /**
     * @return array{sid: string, sub: string}
     */
    public function validate(string $logoutToken): array
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($logoutToken);
        } catch (Throwable $e) {
            throw new OidcClientException('The logout token could not be parsed.', 0, $e);
        }

        if (! $token instanceof UnencryptedToken) {
            throw new OidcClientException('The logout token is not a signed JWT.');
        }

        if ($token->headers()->get('typ') !== 'logout+jwt') {
            throw new OidcClientException('The logout token has an invalid typ header.');
        }

        $kid = $token->headers()->get('kid');
        if (! is_string($kid)) {
            throw new OidcClientException('The logout token has no kid header.');
        }

        $pem = $this->keys->publicKeyPem($kid);
        if (! (new Validator)->validate($token, new SignedWith(new Sha256, InMemory::plainText($pem)))) {
            throw new OidcClientException('The logout token signature is invalid.');
        }

        $claims = $token->claims();
        $leeway = (int) config('oidc-client.leeway', 60);
        $now = time();

        if (rtrim((string) $claims->get('iss'), '/') !== rtrim((string) config('oidc-client.issuer'), '/')) {
            throw new OidcClientException('The logout token issuer does not match.');
        }

        $clientId = (string) config('oidc-client.client_id');
        if (! in_array($clientId, (array) $claims->get('aud', []), true)) {
            throw new OidcClientException('The logout token audience does not include this client.');
        }

        if ($claims->has('nonce')) {
            throw new OidcClientException('A logout token must not contain a nonce.');
        }

        $events = $claims->get('events');
        $events = is_object($events) ? (array) $events : $events;
        if (! is_array($events) || ! array_key_exists(self::EVENT, $events)) {
            throw new OidcClientException('The logout token is missing the back-channel logout event.');
        }

        $exp = $claims->get('exp');
        $expTs = $exp instanceof DateTimeInterface ? $exp->getTimestamp() : (is_numeric($exp) ? (int) $exp : null);
        if ($expTs === null) {
            throw new OidcClientException('The logout token is missing an exp claim.');
        }
        if ($now > $expTs + $leeway) {
            throw new OidcClientException('The logout token has expired.');
        }

        $iat = $claims->get('iat');
        if (! ($iat instanceof DateTimeInterface) && ! is_numeric($iat)) {
            throw new OidcClientException('The logout token is missing an iat claim.');
        }
        $iatTs = $iat instanceof DateTimeInterface ? $iat->getTimestamp() : (int) $iat;
        if ($now - $iatTs > $leeway + 300) {
            throw new OidcClientException('The logout token was issued too long ago.');
        }

        $sid = $claims->get('sid');
        if (! is_string($sid) || $sid === '') {
            throw new OidcClientException('The logout token is missing a sid.');
        }

        $sub = $claims->get('sub');

        return ['sid' => $sid, 'sub' => is_string($sub) ? $sub : ''];
    }
}
