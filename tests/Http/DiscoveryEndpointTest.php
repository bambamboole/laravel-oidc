<?php

declare(strict_types=1);

it('serves a spec-compliant discovery document', function () {
    config(['app.url' => 'https://op.test', 'oidc.issuer' => null]);

    $response = $this->getJson('/.well-known/openid-configuration')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public');

    $response->assertJson([
        'issuer' => 'https://op.test',
        'response_types_supported' => ['code'],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
    ]);

    expect($response->json('authorization_endpoint'))->toContain('/oauth/authorize')
        ->and($response->json('token_endpoint'))->toContain('/oauth/token')
        ->and($response->json('jwks_uri'))->toContain('/.well-known/jwks.json')
        ->and($response->json('scopes_supported'))->toContain('openid', 'profile', 'email')
        ->and($response->json('grant_types_supported'))->toContain('authorization_code', 'refresh_token');
});

it('honours a configured issuer and strips trailing slashes', function () {
    config(['oidc.issuer' => 'https://id.example.com/']);

    $this->getJson('/.well-known/openid-configuration')
        ->assertJsonPath('issuer', 'https://id.example.com');
});

it('omits toggled-off endpoints', function () {
    config(['oidc.endpoints.introspection' => false]);

    $this->getJson('/.well-known/openid-configuration')
        ->assertJsonMissingPath('introspection_endpoint');
});
