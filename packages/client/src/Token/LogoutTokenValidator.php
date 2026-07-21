<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Token;

use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Lcobucci\JWT\UnencryptedToken;

class LogoutTokenValidator extends TokenValidator
{
    private const string EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    /**
     * @return array{sid: string, sub: string}
     */
    public function validate(string $logoutToken): array
    {
        $token = $this->parseAndVerifySignature($logoutToken);

        $claims = $token->claims();
        $leeway = $this->leeway();
        $now = time();

        $this->assertIssuer($token);
        $this->assertAudience($token);

        if ($claims->has('nonce')) {
            throw new OidcClientException('A logout token must not contain a nonce.');
        }

        $events = $claims->get('events');
        $events = is_object($events) ? (array) $events : $events;
        if (! is_array($events) || ! array_key_exists(self::EVENT, $events)) {
            throw new OidcClientException('The logout token is missing the back-channel logout event.');
        }

        $exp = $this->timestamp($claims->get('exp'), 'exp', required: true);
        if ($now > $exp + $leeway) {
            throw new OidcClientException('The logout token has expired.');
        }

        $iat = $this->timestamp($claims->get('iat'), 'iat', required: true);
        if ($now - $iat > $leeway + 300) {
            throw new OidcClientException('The logout token was issued too long ago.');
        }

        $sid = $claims->get('sid');
        if (! is_string($sid) || $sid === '') {
            throw new OidcClientException('The logout token is missing a sid.');
        }

        $sub = $claims->get('sub');

        return ['sid' => $sid, 'sub' => is_string($sub) ? $sub : ''];
    }

    protected function tokenName(): string
    {
        return 'logout token';
    }

    protected function assertHeaders(UnencryptedToken $token): void
    {
        if ($token->headers()->get('typ') !== 'logout+jwt') {
            throw new OidcClientException('The logout token has an invalid typ header.');
        }
    }
}
