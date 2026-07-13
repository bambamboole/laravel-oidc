<?php

declare(strict_types=1);

/**
 * OAuth 2.1 §4.2 (client credentials grant)
 */

use Laravel\Passport\ClientRepository;

beforeEach(function () {
    $this->client = app(ClientRepository::class)->createClientCredentialsGrantClient('M2M');
});

it('issues a client_credentials token with its own configured lifetime', function () {
    config(['oidc.token_lifetimes.client_credentials' => 3600]);

    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->client->id,
        'client_secret' => $this->client->plainSecret,
        'scope' => '',
    ])->assertOk();

    expect($response->json('expires_in'))->toBeLessThanOrEqual(3600)
        ->and($response->json('expires_in'))->toBeGreaterThan(3300);
});
