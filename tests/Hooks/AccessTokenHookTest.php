<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\ClientCredentialsContext;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\CryptKey;

/** @param  string[]  $scopeIds */
function accessTokenFor(string $grantType, array $scopeIds = ['openid']): OidcAccessToken
{
    $token = new OidcAccessToken('42', array_map(fn ($s) => new Scope($s), $scopeIds), new Client('cid', 'RP', ['https://rp.test/cb']));
    $token->setIdentifier('tid');
    $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
    $token->setPrivateKey(new CryptKey(__DIR__.'/../fixtures/oauth-private.key', null, false));
    $token->setGrantType($grantType);

    return $token;
}

function parseHookedToken(string $jwt): UnencryptedToken
{
    $token = (new Parser(new JoseEncoder))->parse($jwt);

    if (! $token instanceof UnencryptedToken) {
        throw new RuntimeException('Expected an unencrypted token.');
    }

    return $token;
}

it('runs the client-credentials hook and merges custom access-token claims', function () {
    Oidc::onClientCredentials(fn (ClientCredentialsContext $c) => $c->accessToken->set('org', 'acme'));

    $parsed = parseHookedToken(accessTokenFor('client_credentials')->toString());

    expect($parsed->claims()->get('org'))->toBe('acme');
});

it('runs no hook for the authorization_code grant (claims flow through the context store instead)', function () {
    $parsed = parseHookedToken(accessTokenFor('authorization_code')->toString());

    expect($parsed->claims()->has('via'))->toBeFalse();
});

it('does not let a hook override a protected access-token claim', function () {
    Oidc::onClientCredentials(fn (ClientCredentialsContext $c) => $c->accessToken->set('sub', 'evil')->set('scope', 'admin'));

    $parsed = parseHookedToken(accessTokenFor('client_credentials', ['openid'])->toString());

    expect($parsed->claims()->get('sub'))->toBe('42')
        ->and($parsed->claims()->get('scope'))->toBe('openid');
});

it('adds only base claims when the grant type is unresolved', function () {
    Oidc::onClientCredentials(fn (ClientCredentialsContext $c) => $c->accessToken->set('x', 1));

    $token = accessTokenFor('client_credentials');
    $token->setGrantType(null);

    $parsed = parseHookedToken($token->toString());
    expect($parsed->claims()->has('x'))->toBeFalse();
});
