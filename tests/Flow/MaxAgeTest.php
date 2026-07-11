<?php
declare(strict_types=1);

/**
 * OpenID Connect Core 1.0 §3.1.2.1 (max_age, prompt), §2 (auth_time), §3.1.2.6 (login_required/consent_required)
 */

use Illuminate\Auth\Events\Login;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

beforeEach(function () {
    Passport::authorizationView(fn (array $parameters) => response()->json(['authToken' => $parameters['authToken']]));
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/callback']);

    $verifier = str_repeat('v', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->query = http_build_query([
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);
});

it('forces re-authentication when the session is older than max_age', function () {
    $this->actingAs($this->user)
        ->withSession(['oidc.auth_time' => time() - 3600])
        ->get('/oauth/authorize?'.$this->query.'&max_age=300')
        ->assertRedirect();

    expect(auth()->guest())->toBeTrue();
});

it('proceeds when the session is fresh enough for max_age', function () {
    $this->actingAs($this->user)
        ->withSession(['oidc.auth_time' => time() - 60])
        ->get('/oauth/authorize?'.$this->query.'&max_age=300')
        ->assertOk();
});

it('treats a missing auth_time as stale', function () {
    $this->actingAs($this->user)
        ->get('/oauth/authorize?'.$this->query.'&max_age=300')
        ->assertRedirect();
});

it('does not log out when max_age references an unknown client', function () {
    $query = http_build_query([
        'client_id' => 'this-client-does-not-exist',
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['oidc.auth_time' => time() - 3600])
        ->get('/oauth/authorize?'.$query.'&max_age=1');

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and(auth()->check())->toBeTrue();
});

it('forces re-authentication when max_age is zero', function () {
    $this->actingAs($this->user)
        ->withSession(['oidc.auth_time' => time()])
        ->get('/oauth/authorize?'.$this->query.'&max_age=0')
        ->assertRedirect();

    expect(auth()->guest())->toBeTrue();
});

it('records auth_time in the session on login', function () {
    $this->startSession();

    event(new Login('web', $this->user, false));

    expect(session('oidc.auth_time'))->toBeInt()->toBeGreaterThan(time() - 5);
});

it('returns login_required for prompt=none guests', function () {
    $response = $this->get('/oauth/authorize?'.$this->query.'&prompt=none');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=login_required');
});

it('returns consent_required for prompt=none without prior grant', function () {
    $response = $this->actingAs($this->user)
        ->withSession(['oidc.auth_time' => time()])
        ->get('/oauth/authorize?'.$this->query.'&prompt=none');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=consent_required');
});
