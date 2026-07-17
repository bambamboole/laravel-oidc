<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Testing;

use Bambamboole\LaravelOidcClient\BackchannelLogoutStore;
use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Bambamboole\LaravelOidcClient\RelyingParty;
use Bambamboole\LaravelOidcClient\Routing\Handler;
use Bambamboole\LaravelOidcClient\Token\IdTokenValidator;
use Bambamboole\LaravelOidcClient\Token\JwksKeyResolver;
use Bambamboole\LaravelOidcClient\Token\LogoutTokenValidator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fake OpenID provider for relying-party tests. Install with
 * {@see OidcClient::fake()}: it stubs the
 * issuer's discovery, JWKS, token, and end-session endpoints against a test RSA
 * keypair and returns this object for token minting, callback seeding, and flow
 * assertions.
 */
class OidcClientFake
{
    public const string STATE = 'oidc-fake-state';

    public const string NONCE = 'oidc-fake-nonce';

    public const string VERIFIER = 'oidc-fake-verifier';

    public const string SID = 'oidc-fake-sid';

    public const string KID = 'oidc-fake-key';

    private string $issuer;

    private string $clientId;

    private string $subject = 'oidc-fake-subject';

    private bool $endSessionEnabled = true;

    private ?int $failTokenStatus = null;

    private ?FakeOidcProvider $rogueProvider = null;

    /** @var array<string, mixed> */
    private array $defaultClaims = [];

    public function __construct(private readonly FakeOidcProvider $provider)
    {
        $this->issuer = rtrim((string) (config('oidc-client.issuer') ?: 'https://oidc.test'), '/');
        $this->clientId = (string) (config('oidc-client.client_id') ?: 'oidc-client-test');
    }

    public static function start(): self
    {
        $fake = new self(new FakeOidcProvider);
        $fake->reset();
        $fake->applyHttpStubs();

        app()->instance(self::class, $fake);

        return $fake;
    }

    public function forUser(Authenticatable $user): static
    {
        $this->subject = (string) $user->getAuthIdentifier();
        $this->applyHttpStubs();

        return $this;
    }

    public function issuer(string $issuer): static
    {
        $this->issuer = rtrim($issuer, '/');
        $this->reset();
        $this->applyHttpStubs();

        return $this;
    }

    public function clientId(string $clientId): static
    {
        $this->clientId = $clientId;
        config()->set('oidc-client.client_id', $clientId);
        $this->applyHttpStubs();

        return $this;
    }

    public function withoutEndSessionEndpoint(): static
    {
        $this->endSessionEnabled = false;
        $this->applyHttpStubs();

        return $this;
    }

    public function failTokenExchange(int $status = 400): static
    {
        $this->failTokenStatus = $status;
        $this->applyHttpStubs();

        return $this;
    }

    public function withInvalidSignature(): static
    {
        $this->rogueProvider = new FakeOidcProvider;
        $this->applyHttpStubs();

        return $this;
    }

    /**
     * Seed values for the callback session triplet. Pass the result to the
     * test's withSession(): the facade fake cannot inject request session state.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public function callbackContext(array $overrides = []): array
    {
        return array_merge([
            'oidc-client.state' => self::STATE,
            'oidc-client.nonce' => self::NONCE,
            'oidc-client.code_verifier' => self::VERIFIER,
        ], $overrides);
    }

    /**
     * The callback URL carrying a fake authorization code and the seeded state.
     *
     * @param  array<string, string>  $query
     */
    public function callbackUrl(array $query = []): string
    {
        return route(Handler::Callback->value, array_merge([
            'code' => 'oidc-fake-code',
            'state' => self::STATE,
        ], $query));
    }

    /**
     * Point the token endpoint's id_token at $user and return the callback URL.
     * Seed the session with callbackContext() in the same chain.
     *
     * @param  array<string, mixed>  $claims
     */
    public function loginAs(Authenticatable $user, array $claims = []): string
    {
        $this->subject = (string) $user->getAuthIdentifier();
        $this->defaultClaims = array_merge($this->defaultClaims, $claims);
        $this->applyHttpStubs();

        return $this->callbackUrl();
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function idToken(array $claims = []): string
    {
        $signer = $this->rogueProvider ?? $this->provider;

        return $signer->idToken(array_merge($this->defaultIdTokenClaims(), $this->defaultClaims, $claims), self::KID);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function logoutToken(array $claims = []): string
    {
        return $this->provider->logoutToken(array_merge([
            'iss' => $this->issuer,
            'aud' => $this->clientId,
            'sub' => $this->subject,
            'sid' => self::SID,
            'iat' => time(),
            'exp' => time() + 300,
            'jti' => 'oidc-fake-jti',
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => (object) []],
        ], $claims), self::KID);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultIdTokenClaims(): array
    {
        return [
            'iss' => $this->issuer,
            'aud' => $this->clientId,
            'sub' => $this->subject,
            'nonce' => self::NONCE,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 300,
        ];
    }

    private function reset(): void
    {
        config()->set('oidc-client.issuer', $this->issuer);
        config()->set('oidc-client.client_id', $this->clientId);

        if (! is_string(config('oidc-client.redirect_uri')) || config('oidc-client.redirect_uri') === '') {
            config()->set('oidc-client.redirect_uri', 'https://app.test/login/callback');
        }

        foreach ([OidcDiscovery::class, JwksKeyResolver::class, RelyingParty::class, IdTokenValidator::class, LogoutTokenValidator::class] as $abstract) {
            app()->forgetInstance($abstract);
        }

        Cache::forget('oidc-client:discovery:'.$this->issuer);
        Cache::forget('oidc-client:jwks:'.$this->issuer);
    }

    private function applyHttpStubs(): void
    {
        // Http::fake() accumulates stubs and resolves the first matching one,
        // so a re-stub with changed responses (e.g. the failure customizers)
        // would otherwise merge behind the stubs registered by start(). Drop
        // the singleton factory first so each re-stub fully replaces the prior.
        app()->forgetInstance(HttpFactory::class);
        Http::clearResolvedInstance(HttpFactory::class);

        $discovery = [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->issuer.'/oauth/authorize',
            'token_endpoint' => $this->issuer.'/oauth/token',
            'jwks_uri' => $this->issuer.'/.well-known/jwks.json',
        ];

        if ($this->endSessionEnabled) {
            $discovery['end_session_endpoint'] = $this->issuer.'/oauth/logout';
        }

        $token = $this->failTokenStatus !== null
            ? Http::response([], $this->failTokenStatus)
            : Http::response([
                'access_token' => 'oidc-fake-access-token',
                'refresh_token' => 'oidc-fake-refresh-token',
                'id_token' => $this->idToken(),
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]);

        Http::fake([
            $this->issuer.'/.well-known/openid-configuration' => Http::response($discovery),
            $this->issuer.'/.well-known/jwks.json' => Http::response(['keys' => $this->provider->rsaJwks(self::KID)]),
            $this->issuer.'/oauth/token' => $token,
            $this->issuer.'/oauth/logout' => Http::response('', 200),
        ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    public function assertRedirectedToProvider(TestResponse $response): static
    {
        $location = (string) $response->headers->get('Location');

        Assert::assertStringStartsWith($this->issuer.'/oauth/authorize', $location);
        Assert::assertStringContainsString('response_type=code', $location);
        Assert::assertStringContainsString('code_challenge_method=S256', $location);
        Assert::assertStringContainsString('client_id='.rawurlencode($this->clientId), $location);

        return $this;
    }

    public function assertLoggedIn(Authenticatable $user): static
    {
        $guard = Auth::guard((string) config('oidc-client.login_guard', 'web'));

        Assert::assertTrue($guard->check(), 'The login guard is not authenticated.');
        Assert::assertSame((string) $user->getAuthIdentifier(), (string) $guard->id());

        return $this;
    }

    public function assertBackchannelLogoutProcessed(string $sid): static
    {
        Assert::assertTrue(
            app(BackchannelLogoutStore::class)->isRevoked($sid),
            "No back-channel logout was processed for sid [{$sid}].",
        );

        return $this;
    }

    public function assertCodeExchanged(): static
    {
        Http::assertSent(fn (Request $request): bool => $request->url() === $this->issuer.'/oauth/token');

        return $this;
    }

    public function assertCodeNotExchanged(): static
    {
        Http::assertNotSent(fn (Request $request): bool => $request->url() === $this->issuer.'/oauth/token');

        return $this;
    }
}
