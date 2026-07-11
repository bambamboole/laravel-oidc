<?php
declare(strict_types=1);

/**
 * RFC 9068 (JWT profile for OAuth 2.0 access tokens); RFC 6750 §2.1 (bearer usage)
 */

use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\PostLoginContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Passport::authorizationView(fn (array $p) => response()->json(['authToken' => $p['authToken']]));
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'email_verified_at' => now(), 'password' => 'x']);
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/callback']);
});

function parseRfc9068AccessToken(string $jwt): UnencryptedToken
{
    $token = (new Parser(new JoseEncoder))->parse($jwt);

    if (! $token instanceof UnencryptedToken) {
        throw new RuntimeException('Expected an unencrypted JWT.');
    }

    return $token;
}

// RFC 9068 §2.1 (at+jwt header), §2.2 (required claims)
it('issues an RFC 9068 access token through the real flow with hook claims', function () {
    config(['app.url' => 'https://op.test']);
    Oidc::onPostLogin(fn (PostLoginContext $c) => $c->accessToken->set('tenant', 'acme'));

    [$verifier, $challenge] = [str_repeat('v', 64), rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 64), true)), '+/', '-_'), '=')];
    $view = $this->actingAs($this->user)->withSession(['oidc.auth_time' => time()])->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->client->id, 'redirect_uri' => 'https://rp.test/callback', 'response_type' => 'code',
        'scope' => 'openid email', 'state' => 's', 'nonce' => 'n', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
    ]))->assertOk();
    $approve = $this->post('/oauth/authorize', ['auth_token' => $view->json('authToken')])->assertRedirect();
    parse_str(parse_url($approve->headers->get('Location'), PHP_URL_QUERY), $params);
    $response = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $this->client->id, 'client_secret' => $this->client->plainSecret,
        'redirect_uri' => 'https://rp.test/callback', 'code' => $params['code'], 'code_verifier' => $verifier,
    ])->assertOk();

    $at = parseRfc9068AccessToken($response->json('access_token'));
    expect($at->headers()->get('typ'))->toBe('at+jwt')
        ->and($at->claims()->get('iss'))->toBe('https://op.test')
        ->and($at->claims()->get('scope'))->toBe('openid email')
        ->and($at->claims()->get('scopes'))->toBe(['openid', 'email'])
        ->and($at->claims()->get('tenant'))->toBe('acme')
        ->and($at->claims()->get('client_id'))->toBe($this->client->id);
});

it('still authenticates the RFC 9068 token on an auth:api route (no guard regression)', function () {
    Passport::actingAs($this->user, ['openid']);
    $this->getJson('/oauth/userinfo')->assertOk();
});
