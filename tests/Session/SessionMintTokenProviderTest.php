<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Contracts\SessionTokenProvider;
use Bambamboole\LaravelOidc\Token\PassportKeys;
use Bambamboole\LaravelOidc\Token\TokenInspector;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Workbench\App\Models\User;

function parseSessionToken(string $jwt): UnencryptedToken
{
    $parsed = (new Parser(new JoseEncoder))->parse($jwt);

    if (! $parsed instanceof UnencryptedToken) {
        throw new RuntimeException('Expected an unencrypted token.');
    }

    return $parsed;
}

beforeEach(function () {
    $this->appClient = app(ClientRepository::class)->createAuthorizationCodeGrantClient('App', ['https://app.test/cb']);
    config(['oidc.first_party_client' => $this->appClient->id]);
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $this->startSession();
});

it('establishes a persisted root token for the user, stored in the session', function () {
    $this->actingAs($this->user);
    app(SessionTokenProvider::class)->establish($this->user);

    $jwt = session('oidc.session_token')['jwt'] ?? null;
    expect($jwt)->toBeString();

    $parsed = parseSessionToken($jwt);
    expect($parsed->claims()->get('sub'))->toBe((string) $this->user->id)
        ->and($parsed->claims()->get('client_id'))->toBe($this->appClient->id)
        ->and($parsed->claims()->get('aud'))->toBe([$this->appClient->id]);

    expect((new Validator)->validate($parsed, new SignedWith(new Sha256, InMemory::plainText(PassportKeys::publicKey()))))->toBeTrue();
    expect(app(TokenInspector::class)->accessToken($jwt))->not->toBeNull();
});

it('currentToken self-heals by minting when none is stored', function () {
    $this->actingAs($this->user);

    $jwt = app(SessionTokenProvider::class)->currentToken();
    expect($jwt)->toBeString()
        ->and(parseSessionToken($jwt)->claims()->get('sub'))->toBe((string) $this->user->id);
});

it('currentToken returns null without an authenticated user', function () {
    expect(app(SessionTokenProvider::class)->currentToken())->toBeNull();
});

it('forget revokes the root token and clears the session', function () {
    $this->actingAs($this->user);
    app(SessionTokenProvider::class)->establish($this->user);
    $jwt = session('oidc.session_token')['jwt'];

    app(SessionTokenProvider::class)->forget();

    expect(session('oidc.session_token'))->toBeNull();
    $dbToken = app(TokenInspector::class)->accessToken($jwt);
    expect($dbToken === null || (bool) $dbToken->getAttribute('revoked'))->toBeTrue();
});
