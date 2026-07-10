<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Token\IdTokenBuilder;
use Bambamboole\LaravelOidc\Token\Jwk;
use Bambamboole\LaravelOidc\Token\PassportKeys;
use Laravel\Passport\Bridge\AccessToken;
use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\Scope as BridgeScope;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use League\OAuth2\Server\CryptKey;
use Workbench\App\Models\User;

function escapedFixtureKey(string $file): string
{
    return str_replace("\n", '\n', trim((string) file_get_contents(__DIR__.'/../fixtures/'.$file)));
}

it('resolves keys from passport config with escaped newlines', function () {
    config(['passport.public_key' => escapedFixtureKey('oauth-public.key')]);

    expect(PassportKeys::publicKey())
        ->toBe(trim((string) file_get_contents(__DIR__.'/../fixtures/oauth-public.key')));
});

it('falls back to key files when no config key is set', function () {
    config(['passport.public_key' => null]);

    expect(PassportKeys::publicKey())
        ->toBe(file_get_contents(__DIR__.'/../fixtures/oauth-public.key'));
});

it('fails loud when neither config key nor key file exists', function () {
    config(['passport.private_key' => null]);
    Passport::loadKeysFrom('/nonexistent');

    PassportKeys::privateKey();
})->throws(RuntimeException::class, 'PASSPORT_PRIVATE_KEY');

it('serves the same jwks from an env-provided key', function () {
    $fromFile = Jwk::fromPem((string) file_get_contents(__DIR__.'/../fixtures/oauth-public.key'));

    config(['passport.public_key' => escapedFixtureKey('oauth-public.key')]);

    $this->getJson('/.well-known/jwks.json')
        ->assertOk()
        ->assertJsonPath('keys.0.kid', $fromFile['kid']);
});

it('signs id_tokens with env-provided keys', function () {
    config([
        'passport.private_key' => escapedFixtureKey('oauth-private.key'),
        'passport.public_key' => escapedFixtureKey('oauth-public.key'),
    ]);

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $client = new BridgeClient('client-uuid', 'RP', ['https://rp.test/callback']);
    $accessToken = new AccessToken((string) $user->id, [new BridgeScope('openid')], $client);
    $accessToken->setIdentifier('env-token-id');
    $accessToken->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
    $accessToken->setPrivateKey(new CryptKey(__DIR__.'/../fixtures/oauth-private.key', null, false));

    $jwt = app(IdTokenBuilder::class)->build($accessToken, null, null);

    $parsed = (new Parser(new JoseEncoder))->parse($jwt);
    $valid = (new Validator)->validate($parsed, new SignedWith(
        new Sha256,
        InMemory::plainText(PassportKeys::publicKey()),
    ));

    expect($valid)->toBeTrue()
        ->and($parsed->headers()->get('kid'))
        ->toBe(Jwk::fromPem(PassportKeys::publicKey())['kid']);
});
