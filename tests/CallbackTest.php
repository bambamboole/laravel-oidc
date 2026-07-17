<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Bambamboole\LaravelOidcClient\Testing\OidcClientFake;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;

beforeEach(function () {
    Auth::forgetGuards();
    Cache::clear();
    config()->set('oidc-client.client_secret', 'secret-xyz');
    config()->set('oidc-client.redirect_after_login', '/dashboard');

    $this->fake = OidcClient::fake()->clientId('client-123');
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
});

it('completes the callback and logs the user into the web guard', function () {
    $this->withSession($this->fake->callbackContext())
        ->get($this->fake->loginAs($this->user))
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);

    Http::assertSent(fn ($request) => $request->url() === config('oidc-client.issuer').'/oauth/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code_verifier'] === OidcClientFake::VERIFIER
        && $request['client_secret'] === 'secret-xyz');
});

it('records the sid and a session pointer when backchannel logout is enabled', function () {
    config()->set('oidc-client.backchannel_logout.enabled', true);

    $this->withSession($this->fake->callbackContext())
        ->get($this->fake->loginAs($this->user, ['sid' => 'the-sid']))
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
    expect(session('oidc-client.sid'))->toBe('the-sid');
    expect(Cache::has('oidc-client:bclo:session:the-sid'))->toBeTrue();
});

it('rejects a tampered state and does not log in', function () {
    $this->withSession($this->fake->callbackContext())
        ->get($this->fake->callbackUrl(['state' => 'WRONG-state']))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->fake->assertCodeNotExchanged();
});

it('rejects missing or empty callback session context before discovery or token exchange', function (array $context) {
    $this->withSession($context)
        ->get($this->fake->callbackUrl())
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->fake->assertCodeNotExchanged();
})->with([
    'missing context' => [[]],
    'missing state' => [[
        'oidc-client.nonce' => OidcClientFake::NONCE,
        'oidc-client.code_verifier' => OidcClientFake::VERIFIER,
    ]],
    'empty state' => [[
        'oidc-client.state' => '',
        'oidc-client.nonce' => OidcClientFake::NONCE,
        'oidc-client.code_verifier' => OidcClientFake::VERIFIER,
    ]],
    'missing nonce' => [[
        'oidc-client.state' => OidcClientFake::STATE,
        'oidc-client.code_verifier' => OidcClientFake::VERIFIER,
    ]],
    'empty nonce' => [[
        'oidc-client.state' => OidcClientFake::STATE,
        'oidc-client.nonce' => '',
        'oidc-client.code_verifier' => OidcClientFake::VERIFIER,
    ]],
    'missing verifier' => [[
        'oidc-client.state' => OidcClientFake::STATE,
        'oidc-client.nonce' => OidcClientFake::NONCE,
    ]],
    'empty verifier' => [[
        'oidc-client.state' => OidcClientFake::STATE,
        'oidc-client.nonce' => OidcClientFake::NONCE,
        'oidc-client.code_verifier' => '',
    ]],
]);

it('rejects replayed callback session context before discovery or token exchange', function () {
    $this->withSession($this->fake->callbackContext())
        ->get($this->fake->callbackUrl(['state' => 'WRONG-state']))
        ->assertRedirect(route('login'));

    $this->get($this->fake->callbackUrl())
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->fake->assertCodeNotExchanged();
});

it('rejects a failed token exchange and does not log in', function () {
    $this->fake->failTokenExchange(400);

    $this->withSession($this->fake->callbackContext())
        ->get($this->fake->callbackUrl())
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects an id token with a tampered signature and does not log in', function () {
    $this->fake->withInvalidSignature();

    $this->withSession($this->fake->callbackContext())
        ->get($this->fake->callbackUrl())
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
