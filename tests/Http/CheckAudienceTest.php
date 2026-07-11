<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Middleware\CheckAudience;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\ClientRepository;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/cb']);

    Route::middleware(['auth:api', CheckAudience::using('https://api.internal/orders')])
        ->get('/test/orders', fn () => response()->json(['ok' => true]));
});

it('passes a token whose aud contains the required audience', function () {
    $jwt = persistedBearer($this, ['https://api.internal/orders']);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertOk();
});

it('rejects a token whose aud lacks the required audience', function () {
    $jwt = persistedBearer($this, ['https://other/api']);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertForbidden();
});

it('rejects an id_token presented as a bearer (typ is not at+jwt)', function () {
    $jwt = persistedIdTokenAsBearer($this);

    $this->getJson('/test/orders', ['Authorization' => "Bearer $jwt"])->assertForbidden();
});
