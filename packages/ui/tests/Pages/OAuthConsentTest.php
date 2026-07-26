<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\Views\ConsentPrompt;
use Bambamboole\LaravelOidc\Server\Scopes\Scope;
use Bambamboole\LaravelOidc\Server\Testing\InteractsWithOidc;
use Bambamboole\LaravelOidc\Ui\Pages\OAuthConsentPage;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Workbench\App\Models\User;

uses(InteractsWithOidc::class);

it('renders the consent page for an authorization request', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);
    $pkce = $this->pkce();

    $this->actingAsIdentity($user)
        ->get(route('oidc.authorize', [
            'client_id' => $client->id,
            'redirect_uri' => 'https://rp.test/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => 'st4te',
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
        ]), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee($client->name, false);
});

it('renders for a non-Eloquent user without leaking a null email into the translation', function () {
    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);
    $user = new GenericUser(['id' => 1]);

    $prompt = new ConsentPrompt(
        client: $client,
        user: $user,
        scopes: [new Scope('openid', 'OpenID Connect')],
        authToken: 'test-auth-token',
    );

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $response = (new OAuthConsentPage($prompt))->toResponse($request);
    $content = $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toContain(__('oidc-ui::oauth.consent.signed-in-as', ['email' => '']))
        ->and($content)->not->toContain(__('oidc-ui::oauth.consent.signed-in-as', ['email' => 'null']));
});

it('does not render hidden scopes', function () {
    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);

    $prompt = new ConsentPrompt(
        client: $client,
        user: new GenericUser(['id' => 1]),
        scopes: [
            new Scope('openid', 'OpenID Connect'),
            new Scope('internal:metrics', 'Internal metrics access', hidden: true),
        ],
        authToken: 'test-auth-token',
    );

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $content = (new OAuthConsentPage($prompt))->toResponse($request)->getContent();

    expect($content)->toContain('OpenID Connect')
        ->and($content)->not->toContain('Internal metrics access');
});

it('omits the scopes heading when only hidden scopes are requested', function () {
    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);

    $prompt = new ConsentPrompt(
        client: $client,
        user: new GenericUser(['id' => 1]),
        scopes: [new Scope('internal:metrics', 'Internal metrics access', hidden: true)],
        authToken: 'test-auth-token',
    );

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $content = (new OAuthConsentPage($prompt))->toResponse($request)->getContent();

    expect($content)->not->toContain(__('oidc-ui::oauth.consent.requested-scopes'));
});

it('throws when rendered without the consent prompt', function () {
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    expect(fn () => (new OAuthConsentPage)->toResponse($request))
        ->toThrow(LogicException::class, 'rendered without its prompt');
});

it('redirects guests to login', function () {
    config(['oidc.login_route' => 'identity.login']);

    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);
    $pkce = $this->pkce();

    $this->get(route('oidc.authorize', [
        'client_id' => $client->id,
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'st4te',
        'code_challenge' => $pkce->challenge,
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('identity.login'));
});
