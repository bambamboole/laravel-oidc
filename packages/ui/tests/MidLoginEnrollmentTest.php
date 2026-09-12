<?php
declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

/**
 * A realm that always requires a second factor sends a user without one to
 * enrollment before any session exists, so every request in this flow is a
 * guest request by design. The screen's components have to call back into
 * endpoints that do not demand a session either.
 */
function parkedOnEnrollment(): void
{
    config(['oidc.authentication.mfa' => 'always']);

    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    test()->post(route('identity.login.store'), ['email' => 'm@example.com', 'password' => 'password'])
        ->assertRedirect(route('identity.two-factor.setup'));

    test()->assertGuest('identity');
    test()->assertGuest();
}

/**
 * @param  array<string, mixed>  $node
 * @return array<string, mixed>
 */
function formNode(array $node): array
{
    if (($node['type'] ?? null) === 'form') {
        return $node;
    }

    foreach ($node['schema'] ?? [] as $child) {
        if (is_array($child) && ($found = formNode($child)) !== []) {
            return $found;
        }
    }

    return [];
}

it('renders the enrollment screen mid-login', function (): void {
    parkedOnEnrollment();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('identity.two-factor.setup'))
        ->assertOk();
});

it('points the enrollment wizard at endpoints a guest can reach', function (): void {
    parkedOnEnrollment();

    $form = formNode($this->withHeader('X-Inertia', 'true')
        ->get(route('identity.two-factor.setup'))
        ->assertOk()
        ->json('props.lattice'));

    expect($form['props']['action'])->toBe('/oidc-ui/forms/oidc.two-factor.setup');

    $endpoint = Route::getRoutes()->match(
        Request::create($form['props']['action'], 'POST'),
    );

    expect($endpoint->gatherMiddleware())->not->toContain('auth');
});

it('serves the default endpoints behind the session they assume', function (): void {
    $default = Route::getRoutes()->match(
        Request::create('/lattice/forms/oidc.two-factor.setup', 'POST'),
    );

    expect($default->gatherMiddleware())->toBe(config('lattice.forms.middleware'));
});
