<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Controllers\ApproveAuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\AuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\DenyAuthorizationController;
use Bambamboole\LaravelOidc\Tests\TestCase;
use Bambamboole\LaravelOidc\Token\PassportKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Passport::authorizationView(fn (array $parameters) => response()->json([
        'authToken' => $parameters['authToken'],
        'scopes' => $parameters['scopes'],
    ]));

    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'email_verified_at' => now(), 'password' => 'x']);
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/callback']);
});

/**
 * @return array{0: string, 1: string}
 */
function pkcePair(): array
{
    $verifier = str_repeat('v', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function parseIdToken(string $jwt): UnencryptedToken
{
    $token = (new Parser(new JoseEncoder))->parse($jwt);

    if (! $token instanceof UnencryptedToken) {
        throw new RuntimeException('Expected an unencrypted JWT.');
    }

    return $token;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return TestResponse<JsonResponse>
 */
function completeAuthorization(TestCase $test, array $overrides = []): TestResponse
{
    [$verifier, $challenge] = pkcePair();

    $query = array_merge([
        'client_id' => $test->client->id,
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid email',
        'state' => 'st4te',
        'nonce' => 'n0nce',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], $overrides);

    $view = $test->actingAs($test->user)
        ->withSession(['oidc.auth_time' => time() - 60])
        ->get('/oauth/authorize?'.http_build_query($query))
        ->assertOk();

    $approve = $test->post('/oauth/authorize', ['auth_token' => $view->json('authToken')])
        ->assertRedirect();

    parse_str(parse_url($approve->headers->get('Location'), PHP_URL_QUERY), $params);

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $test->client->id,
        'client_secret' => $test->client->plainSecret,
        'redirect_uri' => 'https://rp.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ]);
}

it('issues an id_token through the full code + pkce flow', function () {
    config(['app.url' => 'https://op.test', 'oidc.issuer' => null]);

    $response = completeAuthorization($this)->assertOk();

    expect($response->json())->toHaveKeys(['access_token', 'refresh_token', 'id_token']);

    $idToken = parseIdToken($response->json('id_token'));

    expect($idToken->claims()->get('iss'))->toBe('https://op.test')
        ->and($idToken->claims()->get('sub'))->toBe((string) $this->user->id)
        ->and($idToken->claims()->get('aud'))->toBe([$this->client->id])
        ->and($idToken->claims()->get('nonce'))->toBe('n0nce')
        ->and($idToken->claims()->get('auth_time'))->toBeInt()
        ->and($idToken->claims()->get('email'))->toBe('m@example.com');

    $expectedAtHash = rtrim(strtr(base64_encode(
        substr(hash('sha256', $response->json('access_token'), true), 0, 16)
    ), '+/', '-_'), '=');
    expect($idToken->claims()->get('at_hash'))->toBe($expectedAtHash);

    expect((new Validator)->validate($idToken, new SignedWith(
        new Sha256, InMemory::plainText(PassportKeys::publicKey()),
    )))->toBeTrue();

    $jwks = $this->getJson('/.well-known/jwks.json')->json('keys');
    expect($idToken->headers()->get('kid'))->toBe($jwks[0]['kid']);
});

it('omits the id_token without the openid scope', function () {
    $response = completeAuthorization($this, ['scope' => 'email'])->assertOk();

    expect($response->json())->toHaveKey('access_token')
        ->and($response->json())->not->toHaveKey('id_token');
});

it('issues an id_token without nonce or auth_time on refresh', function () {
    $refreshToken = completeAuthorization($this)->json('refresh_token');

    $response = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $this->client->id,
        'client_secret' => $this->client->plainSecret,
        'refresh_token' => $refreshToken,
    ])->assertOk();

    $idToken = parseIdToken($response->json('id_token'));
    expect($idToken->claims()->has('nonce'))->toBeFalse()
        ->and($idToken->claims()->has('auth_time'))->toBeFalse();
});

it('owns the oauth routes with package controllers', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('passport.authorizations.authorize')->getControllerClass())
        ->toBe(AuthorizationController::class)
        ->and($routes->getByName('passport.authorizations.approve')->getControllerClass())
        ->toBe(ApproveAuthorizationController::class)
        ->and($routes->getByName('passport.authorizations.deny')->getControllerClass())
        ->toBe(DenyAuthorizationController::class);

    expect(collect($routes->getRoutes())->filter(
        fn ($route) => $route->uri() === 'oauth/token' && in_array('POST', $route->methods(), true)
    )->count())->toBe(1);
});
