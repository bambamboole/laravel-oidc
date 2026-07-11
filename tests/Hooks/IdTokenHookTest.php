<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\PostLoginContext;
use Bambamboole\LaravelOidc\Hooks\Context\RefreshContext;
use Bambamboole\LaravelOidc\Token\IdTokenBuilder;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\CryptKey;
use Workbench\App\Models\User;

function idTokenAccessToken(): OidcAccessToken
{
    $token = new OidcAccessToken('1', [new Scope('openid')], new Client('cid', 'RP', ['https://rp.test/cb']));
    $token->setIdentifier('tid');
    $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
    $token->setPrivateKey(new CryptKey(__DIR__.'/../fixtures/oauth-private.key', null, false));

    return $token;
}

function parseHookedIdToken(string $jwt): UnencryptedToken
{
    $token = (new Parser(new JoseEncoder))->parse($jwt);

    if (! $token instanceof UnencryptedToken) {
        throw new RuntimeException('Expected an unencrypted token.');
    }

    return $token;
}

it('runs the post-login hook for the authorization_code grant id_token', function () {
    User::create(['id' => 1, 'name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    Oidc::onPostLogin(fn (PostLoginContext $c) => $c->idToken->set('department', 'eng'));

    $jwt = app(IdTokenBuilder::class)->build(idTokenAccessToken(), 'n0nce', 1700000000, 'authorization_code');
    $parsed = parseHookedIdToken($jwt);

    expect($parsed->claims()->get('department'))->toBe('eng')
        ->and($parsed->claims()->get('nonce'))->toBe('n0nce');
});

it('runs the refresh hook for the refresh_token grant', function () {
    User::create(['id' => 1, 'name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    Oidc::onRefresh(fn (RefreshContext $c) => $c->idToken->set('via', 'refresh'));

    $jwt = app(IdTokenBuilder::class)->build(idTokenAccessToken(), null, null, 'refresh_token');
    expect(parseHookedIdToken($jwt)->claims()->get('via'))->toBe('refresh');
});

it('cannot override a protected id_token claim', function () {
    User::create(['id' => 1, 'name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    Oidc::onPostLogin(fn (PostLoginContext $c) => $c->idToken->set('nonce', 'evil')->set('sub', 'evil'));

    $jwt = app(IdTokenBuilder::class)->build(idTokenAccessToken(), 'real', null, 'authorization_code');
    $parsed = parseHookedIdToken($jwt);

    expect($parsed->claims()->get('nonce'))->toBe('real')
        ->and($parsed->claims()->get('sub'))->toBe('1');
});
