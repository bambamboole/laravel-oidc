<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Token\Jwk;

it('serves the public key as a JWKS document', function () {
    $expected = Jwk::fromPem(file_get_contents(__DIR__.'/../fixtures/oauth-public.key'));

    $this->getJson('/.well-known/jwks.json')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertJson(['keys' => [$expected]]);
});

it('includes additional configured public keys', function () {
    config(['oidc.additional_public_keys' => [file_get_contents(__DIR__.'/../fixtures/oauth-public.key')]]);

    $this->getJson('/.well-known/jwks.json')->assertJsonCount(2, 'keys');
});
