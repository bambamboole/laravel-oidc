<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Testing;

use Bambamboole\LaravelOidc\Auth\AuthenticationMethods;
use Bambamboole\LaravelOidc\Token\AccessTokenMinter;
use DateInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Test helpers for consumers of the package. Add to your Pest suite with
 * `uses(InteractsWithOidc::class)` (or `use` it in a PHPUnit TestCase).
 *
 * The host class must be a Laravel HTTP test case (Illuminate's
 * `Illuminate\Foundation\Testing\TestCase` or Orchestra Testbench's) — the
 * abstract method declarations below pin exactly what the trait needs.
 */
trait InteractsWithOidc
{
    private ?Client $oidcDefaultClient = null;

    /**
     * @return static
     */
    abstract public function actingAs(Authenticatable $user, $guard = null);

    /**
     * @param  array<string, mixed>  $data
     * @return static
     */
    abstract public function withSession(array $data);

    /**
     * Authenticate on the identity guard and seed the session keys the
     * authorization grant reads: `oidc.auth_time`, `oidc.amr`,
     * `oidc.id_token_claims`, `oidc.access_token_claims`.
     *
     * `acr` is intentionally not a parameter: the grant derives it from
     * `$amr` via {@see AuthenticationMethods::deriveAcr()}.
     *
     * @param  array<string, mixed>  $idTokenClaims
     * @param  array<string, mixed>  $accessTokenClaims
     * @param  list<string>  $amr
     */
    public function actingAsIdentity(
        Authenticatable $user,
        array $idTokenClaims = [],
        array $accessTokenClaims = [],
        array $amr = [],
        ?int $authTime = null,
        ?string $guard = null,
    ): static {
        $this->actingAs($user, $guard ?? (string) config('oidc.auth.guard', 'identity'));

        $session = ['oidc.auth_time' => $authTime ?? time()];

        if ($amr !== []) {
            $session[AuthenticationMethods::SESSION_KEY] = $amr;
        }

        if ($idTokenClaims !== []) {
            $session['oidc.id_token_claims'] = $idTokenClaims;
        }

        if ($accessTokenClaims !== []) {
            $session['oidc.access_token_claims'] = $accessTokenClaims;
        }

        $this->withSession($session);

        return $this;
    }

    /**
     * @param  string[]  $redirectUris
     */
    public function createOidcClient(
        string $name = 'Test Client',
        array $redirectUris = ['https://rp.test/callback'],
        bool $confidential = true,
    ): Client {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, $redirectUris, $confidential);
    }

    /**
     * Create (or adopt) a client and point `oidc.first_party.*` config at it.
     */
    public function withFirstPartyClient(?Client $client = null): Client
    {
        $client ??= $this->createOidcClient('First-Party App', ['https://app.test/callback']);

        config([
            'oidc.first_party.client_id' => (string) $client->getKey(),
            'oidc.first_party.trusted' => true,
        ]);

        return $client;
    }

    public function pkce(): PkcePair
    {
        return PkcePair::generate();
    }

    /**
     * Mint a real signed access token (with a persisted Passport token row)
     * without driving the HTTP authorization flow. Creates and memoizes a
     * default client when none is given.
     *
     * @param  string[]  $scopes
     * @param  string[]  $audience
     */
    public function issueTokenFor(
        Authenticatable $user,
        ?Client $client = null,
        array $scopes = ['openid'],
        array $audience = [],
        ?DateInterval $ttl = null,
    ): string {
        $client ??= $this->oidcDefaultClient ??= $this->createOidcClient();

        return app(AccessTokenMinter::class)->mint(
            (string) $user->getAuthIdentifier(),
            $client,
            $scopes,
            $ttl ?? new DateInterval('PT1H'),
            $audience,
        )->toString();
    }
}
