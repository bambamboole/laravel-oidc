<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Middleware\CheckAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\ClientRepository;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/cb']);

    Route::middleware(CheckAudience::using('https://api.internal/orders'))
        ->get('/test/orders', fn (Request $request) => response()->json([
            'user' => $request->user()?->getAuthIdentifier(),
        ]));
});

it('passes a resource-audience token without auth:api', function () {
    $jwt = resourceServerBearer($this, ['https://api.internal/orders']);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertOk();
});

it('rejects a token whose aud lacks the required audience', function () {
    $jwt = resourceServerBearer($this, ['https://other/api']);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertForbidden();
});

it('rejects an id_token presented as a bearer (typ is not at+jwt)', function () {
    $jwt = persistedIdTokenAsBearer($this);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertUnauthorized();
});

it('rejects a request without a bearer token', function () {
    $this->getJson('/test/orders')->assertUnauthorized();
});

it('rejects a garbage token that fails signature validation', function () {
    $this->getJson('/test/orders', ['Authorization' => 'Bearer garbage'])->assertUnauthorized();
});

it('rejects a revoked token', function () {
    $jwt = resourceServerBearer($this, ['https://api.internal/orders'], revoked: true);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertUnauthorized();
});

it('rejects an expired token', function () {
    $jwt = resourceServerBearer($this, ['https://api.internal/orders'], expired: true);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertUnauthorized();
});

it('resolves the request user from the sub claim', function () {
    $jwt = resourceServerBearer($this, ['https://api.internal/orders']);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])
        ->assertOk()
        ->assertJson(['user' => $this->user->id]);
});
