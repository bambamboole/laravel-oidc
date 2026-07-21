<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests;

use Bambamboole\LaravelOidcClient\BackchannelLogoutStore;
use Bambamboole\LaravelOidcClient\Http\Controllers\BackchannelLogoutController;
use Bambamboole\LaravelOidcClient\Testing\FakeOidcProvider;
use Bambamboole\LaravelOidcClient\Tests\Support\BackchannelLogoutEnabledTestCase;
use Bambamboole\LaravelOidcClient\Token\LogoutTokenValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Mockery;

class BackchannelLogoutEndpointTest extends BackchannelLogoutEnabledTestCase
{
    private FakeOidcProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('oidc-client.issuer', 'https://id.example.com');
        config()->set('oidc-client.client_id', 'client-123');
        config()->set('oidc-client.backchannel_logout.enabled', true);
        $this->provider = new FakeOidcProvider;
        Http::fake([
            'https://id.example.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://id.example.com',
                'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
                'token_endpoint' => 'https://id.example.com/oauth/token',
                'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
            ]),
            'https://id.example.com/.well-known/jwks.json' => Http::response(['keys' => $this->provider->rsaJwks('key-1')]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function validLogoutToken(array $overrides = []): string
    {
        return $this->provider->logoutToken(array_merge([
            'iss' => 'https://id.example.com', 'aud' => 'client-123', 'sub' => '42', 'sid' => 'sess-abc',
            'iat' => time(), 'exp' => time() + 120, 'jti' => 'jti-1',
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => (object) []],
        ], $overrides), 'key-1');
    }

    public function test_it_accepts_a_valid_logout_token_marks_the_sid_and_returns_200_no_store(): void
    {
        $response = $this->post('/oidc/backchannel-logout', ['logout_token' => $this->validLogoutToken()]);

        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(Cache::has('oidc-client:bclo:revoked:sess-abc'));
    }

    public function test_it_pulls_the_session_pointer_for_the_sid(): void
    {
        // Mocking the Session facade's getHandler() through the full HTTP kernel proved
        // brittle: unrelated framework internals resolve the `session` container binding
        // (e.g. the `session.store` singleton) and trip the mock's strict expectations.
        // We assert the pointer side effect end-to-end here, and cover the handler
        // `destroy()` call itself with a direct controller invocation below.
        Cache::put('oidc-client:bclo:session:sess-abc', 'the-session-id', now()->addMinutes(120));

        $this->post('/oidc/backchannel-logout', ['logout_token' => $this->validLogoutToken()])->assertOk();

        $this->assertFalse(Cache::has('oidc-client:bclo:session:sess-abc')); // pointer pulled
    }

    public function test_it_destroys_the_session_via_the_session_handler(): void
    {
        Cache::put('oidc-client:bclo:session:sess-abc', 'the-session-id', now()->addMinutes(120));

        // Warm the `redirect`/`url` container bindings first: they lazily resolve
        // `session.store` (=> `session`->driver()) on first use, which would otherwise
        // trip the Session facade mock installed below when the controller calls
        // response() further down.
        response('');

        $handler = Mockery::spy(\SessionHandlerInterface::class);
        Session::shouldReceive('getHandler')->andReturn($handler);

        $request = Request::create('/oidc/backchannel-logout', 'POST', ['logout_token' => $this->validLogoutToken()]);
        $response = app(BackchannelLogoutController::class)->__invoke($request, app(LogoutTokenValidator::class), app(BackchannelLogoutStore::class));

        $this->assertSame(200, $response->getStatusCode());
        $handler->shouldHaveReceived('destroy', ['the-session-id']);
    }

    public function test_it_rejects_an_invalid_logout_token_with_400_and_changes_nothing(): void
    {
        $this->post('/oidc/backchannel-logout', ['logout_token' => 'not-a-jwt'])
            ->assertStatus(400)->assertJson(['error' => 'invalid_request']);
        $this->assertFalse(Cache::has('oidc-client:bclo:revoked:sess-abc'));
    }
}
