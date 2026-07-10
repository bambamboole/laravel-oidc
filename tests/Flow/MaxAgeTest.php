<?php
declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

beforeEach(function () {
    Passport::authorizationView(fn (array $parameters) => response()->json(['authToken' => $parameters['authToken']]));
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/callback']);
    $this->query = http_build_query([
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
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
