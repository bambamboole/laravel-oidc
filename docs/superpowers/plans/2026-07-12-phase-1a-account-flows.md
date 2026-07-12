# Phase 1a Account Flows Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the package-owned Fortify-equivalent account-flow layer for registration, password reset, and email verification.

**Architecture:** The package registers web auth routes and owns the controllers under `Bambamboole\LaravelOidc\Auth\...`. Consuming apps bind renderable views and domain actions through `Oidc::...` seams; package tests bind those seams inside Testbench without using the `saas-starter-kit` app.

**Tech Stack:** PHP 8.4, Laravel 13, Laravel core auth/session/password broker/email verification, Orchestra Testbench, Pest, Pint, PHPStan.

## Global Constraints

- All auth logic lives in this package under namespace `Bambamboole\LaravelOidc`; do not put controllers/routes/actions in a consuming app.
- The consuming app fills only view seams and action seams: package controllers own routing, events, session regeneration, redirects, JSON shapes, and Laravel primitive calls.
- Phase 1a remains single-guard and uses `config('oidc.auth.guard', 'web')`; the two-guard `/auth` identity split is Phase 1b.
- This account-flow slice adds no Composer dependencies; login, logout, password confirmation, rate limiting, TOTP 2FA, recovery codes, passkeys, and new dependencies belong in the follow-up "login + 2FA" plan.
- Preserve Fortify parity from `docs/appside-defortify-parity-reference.md` and the starter-kit `feat/defortify-account-flows` branch: registration email lowercasing, `Registered`, `PasswordReset`, `Verified` events; session regeneration after login/reset; `back()` and JSON response shapes; Fortify-compatible route names plus Lattice `.store` route names.
- Package conventions: `<?php`, blank line, `declare(strict_types=1);`, explicit parameter and return types, constructor property promotion, TitleCase enum keys if enums are added, PHPDoc over narration comments, no narration comments.
- Tests run through the package Testbench harness only; do not require `../saas-starter-kit`.
- Before code changes, use Laravel Boost `search-docs`; this was done for authentication, password reset, email verification, password confirmation, rate limiting, and session regeneration.
- Baseline gates before this plan: `vendor/bin/pest` passed with 163 tests / 445 assertions; `vendor/bin/pint --test` passed; `vendor/bin/phpstan analyse` reported no errors.
- Gate before each task commit: targeted Pest file(s), then `vendor/bin/pint --test`, then `vendor/bin/phpstan analyse`.

---

### Task 1: Account View Seams And GET Routes

**Files:**
- Create: `src/Auth/AuthViewManager.php`
- Create: `src/Auth/Controllers/RegisteredUserController.php`
- Create: `src/Auth/Controllers/PasswordResetLinkController.php`
- Create: `src/Auth/Controllers/NewPasswordController.php`
- Create: `src/Auth/Controllers/EmailVerificationPromptController.php`
- Create: `routes/auth.php`
- Modify: `src/OidcManager.php`
- Modify: `src/Facades/Oidc.php`
- Modify: `src/OidcServiceProvider.php`
- Modify: `config/oidc.php`
- Modify: `workbench/app/Models/User.php`
- Test: `tests/Auth/AuthViewSeamsTest.php`

**Interfaces:**
- Produces: `Oidc::registerView(Closure $view)`, `Oidc::requestPasswordResetLinkView(Closure $view)`, `Oidc::resetPasswordView(Closure $view)`, `Oidc::verifyEmailView(Closure $view)`.
- Produces: GET routes `register`, `password.request`, `password.reset`, and `verification.notice`.

- [ ] **Step 1: Write the failing view-seam route test**

Create `tests/Auth/AuthViewSeamsTest.php`:

```php
<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Http\Request;
use Workbench\App\Models\User;

it('renders account flow views through package seams', function () {
    Oidc::registerView(fn (Request $request) => response('register-view'));
    Oidc::requestPasswordResetLinkView(fn (Request $request) => response('forgot-password-view'));
    Oidc::resetPasswordView(fn (Request $request) => response('reset-password-view:'.$request->route('token')));
    Oidc::verifyEmailView(fn (Request $request) => response('verify-email-view'));

    $this->get('/register')->assertOk()->assertSee('register-view');
    $this->get('/forgot-password')->assertOk()->assertSee('forgot-password-view');
    $this->get('/reset-password/reset-token?email=m@example.com')->assertOk()->assertSee('reset-password-view:reset-token');

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->get('/email/verify')->assertOk()->assertSee('verify-email-view');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Auth/AuthViewSeamsTest.php`

Expected: FAIL because `Oidc::registerView()` and the package account routes do not exist.

- [ ] **Step 3: Add auth config defaults**

Modify `config/oidc.php` by appending this top-level section before the closing `];`:

```php
    'auth' => [
        'enabled' => env('OIDC_AUTH_ENABLED', true),
        'guard' => env('OIDC_AUTH_GUARD', 'web'),
        'home' => env('OIDC_AUTH_HOME', '/dashboard'),
    ],
```

- [ ] **Step 4: Let the workbench user receive auth notifications**

Modify `workbench/app/Models/User.php`:

```php
use Illuminate\Notifications\Notifiable;
```

Update the trait list:

```php
use HasApiTokens, Notifiable;
```

- [ ] **Step 5: Create the view manager**

Create `src/Auth/AuthViewManager.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;

class AuthViewManager
{
    public const string Register = 'register';

    public const string RequestPasswordResetLink = 'request-password-reset-link';

    public const string ResetPassword = 'reset-password';

    public const string VerifyEmail = 'verify-email';

    /**
     * @var array<string, Closure(Request): mixed>
     */
    private array $views = [];

    /**
     * @param  Closure(Request): mixed  $view
     */
    public function bind(string $name, Closure $view): void
    {
        $this->views[$name] = $view;
    }

    public function render(string $name, Request $request): mixed
    {
        $view = $this->views[$name] ?? null;

        if ($view === null) {
            throw new RuntimeException("No OIDC auth view has been configured for [{$name}].");
        }

        return $view($request);
    }
}
```

- [ ] **Step 6: Add GET-only controllers**

Create `src/Auth/Controllers/RegisteredUserController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\Request;

class RegisteredUserController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::Register, $request);
    }
}
```

Create `src/Auth/Controllers/PasswordResetLinkController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\Request;

class PasswordResetLinkController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::RequestPasswordResetLink, $request);
    }
}
```

Create `src/Auth/Controllers/NewPasswordController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\Request;

class NewPasswordController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::ResetPassword, $request);
    }
}
```

Create `src/Auth/Controllers/EmailVerificationPromptController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\Request;

class EmailVerificationPromptController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function __invoke(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::VerifyEmail, $request);
    }
}
```

- [ ] **Step 7: Register GET routes**

Create `routes/auth.php`:

```php
<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Controllers\EmailVerificationPromptController;
use Bambamboole\LaravelOidc\Auth\Controllers\NewPasswordController;
use Bambamboole\LaravelOidc\Auth\Controllers\PasswordResetLinkController;
use Bambamboole\LaravelOidc\Auth\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;

$guard = (string) config('oidc.auth.guard', 'web');

Route::middleware('web')->group(function () use ($guard): void {
    Route::middleware('guest:'.$guard)->group(function (): void {
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    });

    Route::middleware('auth:'.$guard)->group(function (): void {
        Route::get('email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    });
});
```

- [ ] **Step 8: Wire the manager and route file into the package**

Modify `src/OidcServiceProvider.php`:

```php
use Bambamboole\LaravelOidc\Auth\AuthViewManager;
```

Register the singleton in `register()`:

```php
$this->app->singleton(AuthViewManager::class);
```

Load the routes in `boot()` after the existing OIDC route loads:

```php
if (config('oidc.auth.enabled', true)) {
    $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
}
```

- [ ] **Step 9: Expose the view seams on `OidcManager` and facade docs**

Modify `src/OidcManager.php` constructor:

```php
use Bambamboole\LaravelOidc\Auth\AuthViewManager;
```

```php
public function __construct(
    private readonly ClaimHooks $hooks,
    private readonly SessionTokenProvider $sessionTokens,
    private readonly TokenExchanger $exchanger,
    private readonly AuthViewManager $authViews,
) {}
```

Add methods:

```php
public function registerView(Closure $view): void
{
    $this->authViews->bind(AuthViewManager::Register, $view);
}

public function requestPasswordResetLinkView(Closure $view): void
{
    $this->authViews->bind(AuthViewManager::RequestPasswordResetLink, $view);
}

public function resetPasswordView(Closure $view): void
{
    $this->authViews->bind(AuthViewManager::ResetPassword, $view);
}

public function verifyEmailView(Closure $view): void
{
    $this->authViews->bind(AuthViewManager::VerifyEmail, $view);
}

```

Modify `src/Facades/Oidc.php` PHPDoc:

```php
 * @method static void registerView(Closure $view)
 * @method static void requestPasswordResetLinkView(Closure $view)
 * @method static void resetPasswordView(Closure $view)
 * @method static void verifyEmailView(Closure $view)
```

- [ ] **Step 10: Run task verification**

Run: `vendor/bin/pest tests/Auth/AuthViewSeamsTest.php`

Expected: PASS.

Run: `vendor/bin/pint --test`

Expected: PASS.

Run: `vendor/bin/phpstan analyse`

Expected: PASS with zero errors.

- [ ] **Step 11: Commit**

```bash
git add config/oidc.php routes/auth.php src/Auth/AuthViewManager.php src/Auth/Controllers/RegisteredUserController.php src/Auth/Controllers/PasswordResetLinkController.php src/Auth/Controllers/NewPasswordController.php src/Auth/Controllers/EmailVerificationPromptController.php src/OidcManager.php src/Facades/Oidc.php src/OidcServiceProvider.php workbench/app/Models/User.php tests/Auth/AuthViewSeamsTest.php
git commit -m "feat(auth): add package account view seams"
```

---

### Task 2: Registration Action Seam And Controller

**Files:**
- Create: `src/Auth/UserActionManager.php`
- Modify: `src/Auth/Controllers/RegisteredUserController.php`
- Modify: `src/OidcManager.php`
- Modify: `src/Facades/Oidc.php`
- Modify: `src/OidcServiceProvider.php`
- Modify: `routes/auth.php`
- Test: `tests/Auth/RegistrationFlowTest.php`

**Interfaces:**
- Consumes: `AuthViewManager` and `routes/auth.php` from Task 1.
- Produces: `Oidc::createUsersUsing(callable|string $action)`.
- Produces: POST route `register` using `RegisteredUserController::store`, named `register.store` for Lattice page compatibility.

- [ ] **Step 1: Write failing registration tests**

Create `tests/Auth/RegistrationFlowTest.php`:

```php
<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

it('registers a user through the package action seam and logs them in', function () {
    Event::fake([Registered::class]);

    Oidc::createUsersUsing(function (array $input): Authenticatable {
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    });

    $response = $this->from('/register')->post(route('register.store'), [
        'name' => 'M',
        'email' => 'MixedCase@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'mixedcase@example.com')->firstOrFail();

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
});

it('returns Fortify-compatible JSON after registration', function () {
    Oidc::createUsersUsing(function (array $input): Authenticatable {
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    });

    $this->postJson(route('register.store'), [
        'name' => 'M',
        'email' => 'm@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    $this->assertAuthenticated();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Auth/RegistrationFlowTest.php`

Expected: FAIL because `Oidc::createUsersUsing()` and `POST /register` are not implemented.

- [ ] **Step 3: Create the user action manager**

Create `src/Auth/UserActionManager.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use RuntimeException;

class UserActionManager
{
    /**
     * @var callable|class-string|null
     */
    private mixed $createUsersUsing = null;

    /**
     * @var callable|class-string|null
     */
    private mixed $resetUserPasswordsUsing = null;

    /**
     * @param  callable(array<string, mixed>): Authenticatable|class-string  $action
     */
    public function createUsersUsing(callable|string $action): void
    {
        $this->createUsersUsing = $action;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createUser(array $input): Authenticatable
    {
        $action = $this->resolveAction($this->createUsersUsing, 'create user', 'create');

        $user = is_callable($action)
            ? $action($input)
            : $action->create($input);

        if (! $user instanceof Authenticatable) {
            throw new RuntimeException('The OIDC create user action must return an authenticatable user.');
        }

        return $user;
    }

    /**
     * @param  callable(CanResetPassword, array<string, mixed>): void|class-string  $action
     */
    public function resetUserPasswordsUsing(callable|string $action): void
    {
        $this->resetUserPasswordsUsing = $action;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function resetUserPassword(CanResetPassword $user, array $input): void
    {
        $action = $this->resolveAction($this->resetUserPasswordsUsing, 'reset user password', 'reset');

        if (is_callable($action)) {
            $action($user, $input);

            return;
        }

        $action->reset($user, $input);
    }

    private function resolveAction(mixed $action, string $name, string $method): mixed
    {
        if ($action === null) {
            throw new RuntimeException("No OIDC {$name} action has been configured.");
        }

        if (is_string($action) && class_exists($action)) {
            $action = app($action);
        }

        if (is_callable($action)) {
            return $action;
        }

        if (is_object($action) && method_exists($action, $method)) {
            return $action;
        }

        throw new RuntimeException("The configured OIDC {$name} action is not callable.");
    }
}
```

- [ ] **Step 4: Register and expose the action seam**

Modify `src/OidcServiceProvider.php`:

```php
use Bambamboole\LaravelOidc\Auth\UserActionManager;
```

Add in `register()`:

```php
$this->app->singleton(UserActionManager::class);
```

Modify `src/OidcManager.php`:

```php
use Bambamboole\LaravelOidc\Auth\UserActionManager;
```

Update constructor:

```php
public function __construct(
    private readonly ClaimHooks $hooks,
    private readonly SessionTokenProvider $sessionTokens,
    private readonly TokenExchanger $exchanger,
    private readonly AuthViewManager $authViews,
    private readonly UserActionManager $userActions,
) {}
```

Add method:

```php
public function createUsersUsing(callable|string $action): void
{
    $this->userActions->createUsersUsing($action);
}
```

Modify `src/Facades/Oidc.php` PHPDoc:

```php
 * @method static void createUsersUsing(callable|string $action)
```

- [ ] **Step 5: Implement `POST /register`**

Modify `routes/auth.php` inside the guest group:

```php
Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
```

Modify `src/Auth/Controllers/RegisteredUserController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Auth\UserActionManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController
{
    public function __construct(
        private readonly AuthViewManager $views,
        private readonly UserActionManager $actions,
    ) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::Register, $request);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $input = array_merge($request->all(), [
            'email' => $request->string('email')->lower()->value(),
        ]);

        event(new Registered($user = $this->actions->createUser($input)));

        Auth::guard((string) config('oidc.auth.guard', 'web'))->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
    }
}
```

- [ ] **Step 6: Run task verification**

Run: `vendor/bin/pest tests/Auth/RegistrationFlowTest.php`

Expected: PASS.

Run: `vendor/bin/pint --test`

Expected: PASS.

Run: `vendor/bin/phpstan analyse`

Expected: PASS with zero errors.

- [ ] **Step 7: Commit**

```bash
git add src/Auth/UserActionManager.php src/Auth/Controllers/RegisteredUserController.php src/OidcManager.php src/Facades/Oidc.php src/OidcServiceProvider.php routes/auth.php tests/Auth/RegistrationFlowTest.php
git commit -m "feat(auth): own registration through package action seam"
```

---

### Task 3: Password Reset Flow

**Files:**
- Modify: `src/Auth/Controllers/PasswordResetLinkController.php`
- Modify: `src/Auth/Controllers/NewPasswordController.php`
- Modify: `src/OidcManager.php`
- Modify: `src/Facades/Oidc.php`
- Modify: `routes/auth.php`
- Test: `tests/Auth/PasswordResetFlowTest.php`

**Interfaces:**
- Consumes: `UserActionManager` from Task 2.
- Produces: `Oidc::resetUserPasswordsUsing(callable|string $action)`.
- Produces: POST routes `password.email` and `password.update`.

- [ ] **Step 1: Write failing password reset tests**

Create `tests/Auth/PasswordResetFlowTest.php`:

```php
<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Workbench\App\Models\User;

it('sends a password reset link through the Laravel broker', function () {
    Notification::fake();

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('old-password')]);

    $this->from('/forgot-password')
        ->post(route('password.email'), ['email' => 'm@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets a password through the package action seam and logs the user in', function () {
    Event::fake([PasswordReset::class]);

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('old-password')]);
    $token = Password::broker()->createToken($user);

    Oidc::resetUserPasswordsUsing(function (CanResetPassword $user, array $input): void {
        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'm@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));

    $this->assertAuthenticatedAs($user->fresh());
    expect(Hash::check('new-password', (string) $user->fresh()->password))->toBeTrue();
    Event::assertDispatched(PasswordReset::class);
});

it('returns validation errors for an invalid reset token', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('old-password')]);

    Oidc::resetUserPasswordsUsing(function (CanResetPassword $user, array $input): void {
        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    });

    $this->from('/reset-password/invalid-token')
        ->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect('/reset-password/invalid-token')
        ->assertSessionHasErrors('email');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Auth/PasswordResetFlowTest.php`

Expected: FAIL because POST password-reset routes and `Oidc::resetUserPasswordsUsing()` are not implemented.

- [ ] **Step 3: Expose reset action seam**

Modify `src/OidcManager.php`:

```php
public function resetUserPasswordsUsing(callable|string $action): void
{
    $this->userActions->resetUserPasswordsUsing($action);
}
```

Modify `src/Facades/Oidc.php` PHPDoc:

```php
 * @method static void resetUserPasswordsUsing(callable|string $action)
```

- [ ] **Step 4: Add password reset POST routes**

Modify `routes/auth.php` inside the guest group:

```php
Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
```

- [ ] **Step 5: Implement the reset-link controller**

Modify `src/Auth/Controllers/PasswordResetLinkController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::RequestPasswordResetLink, $request);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker((string) config('auth.defaults.passwords', 'users'))
            ->sendResetLink(['email' => $request->string('email')->lower()->value()]);

        if ($status === Password::RESET_LINK_SENT) {
            return $request->wantsJson()
                ? new JsonResponse(['status' => __($status)], 200)
                : back()->with('status', __($status));
        }

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
```

- [ ] **Step 6: Implement the new-password controller**

Modify `src/Auth/Controllers/NewPasswordController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Bambamboole\LaravelOidc\Auth\UserActionManager;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class NewPasswordController
{
    public function __construct(
        private readonly AuthViewManager $views,
        private readonly UserActionManager $actions,
    ) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::ResetPassword, $request);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $status = Password::broker((string) config('auth.defaults.passwords', 'users'))->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (CanResetPassword $user) use ($request): void {
                $this->actions->resetUserPassword($user, $request->all());

                if (method_exists($user, 'setRememberToken')) {
                    $user->setRememberToken(Str::random(60));
                }

                if (method_exists($user, 'save')) {
                    $user->save();
                }

                event(new PasswordReset($user));

                if (! $user instanceof Authenticatable) {
                    throw new RuntimeException('The reset password user must be authenticatable.');
                }

                Auth::guard((string) config('oidc.auth.guard', 'web'))->login($user);
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->regenerate();

            return $request->wantsJson()
                ? new JsonResponse(['status' => __($status)], 200)
                : redirect()->route('login')->with('status', __($status));
        }

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
```

- [ ] **Step 7: Run task verification**

Run: `vendor/bin/pest tests/Auth/PasswordResetFlowTest.php`

Expected: PASS.

Run: `vendor/bin/pint --test`

Expected: PASS.

Run: `vendor/bin/phpstan analyse`

Expected: PASS with zero errors.

- [ ] **Step 8: Commit**

```bash
git add src/Auth/Controllers/PasswordResetLinkController.php src/Auth/Controllers/NewPasswordController.php src/OidcManager.php src/Facades/Oidc.php routes/auth.php tests/Auth/PasswordResetFlowTest.php
git commit -m "feat(auth): own password reset flow"
```

---

### Task 4: Email Verification Flow

**Files:**
- Create: `src/Auth/Controllers/VerifyEmailController.php`
- Create: `src/Auth/Controllers/EmailVerificationNotificationController.php`
- Modify: `src/Auth/Controllers/EmailVerificationPromptController.php`
- Modify: `routes/auth.php`
- Test: `tests/Auth/EmailVerificationFlowTest.php`

**Interfaces:**
- Consumes: `Oidc::verifyEmailView(...)` from Task 1.
- Produces: routes `verification.verify` and `verification.send`.

- [ ] **Step 1: Write failing email verification tests**

Create `tests/Auth/EmailVerificationFlowTest.php`:

```php
<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Workbench\App\Models\User;

it('renders the verification notice for an unverified user', function () {
    Oidc::verifyEmailView(fn (Request $request) => response('verify-email-view'));

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->get('/email/verify')->assertOk()->assertSee('verify-email-view');
});

it('redirects verified users away from the verification notice', function () {
    Oidc::verifyEmailView(fn (Request $request) => response('verify-email-view'));

    $user = User::create([
        'name' => 'M',
        'email' => 'm@example.com',
        'email_verified_at' => now(),
        'password' => 'secret',
    ]);

    $this->actingAs($user)->get('/email/verify')->assertRedirect('/dashboard');
});

it('verifies a signed email verification URL and fires the event', function () {
    Event::fake([Verified::class]);

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect('/dashboard?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('resends the email verification notification', function () {
    Notification::fake();

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->from('/email/verify')
        ->post('/email/verification-notification')
        ->assertRedirect('/email/verify')
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Auth/EmailVerificationFlowTest.php`

Expected: FAIL because verification handler and send routes are missing, and the prompt does not redirect verified users.

- [ ] **Step 3: Add verification routes**

Modify `routes/auth.php` inside the authenticated group:

```php
Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('verification.send');
```

Add imports:

```php
use Bambamboole\LaravelOidc\Auth\Controllers\EmailVerificationNotificationController;
use Bambamboole\LaravelOidc\Auth\Controllers\VerifyEmailController;
```

- [ ] **Step 4: Update the prompt controller**

Modify `src/Auth/Controllers/EmailVerificationPromptController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function __invoke(Request $request): mixed
    {
        $user = $request->user((string) config('oidc.auth.guard', 'web'));

        if (is_object($user) && method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
        }

        return $this->views->render(AuthViewManager::VerifyEmail, $request);
    }
}
```

- [ ] **Step 5: Create verification handler controller**

Create `src/Auth/Controllers/VerifyEmailController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->intended((string) config('oidc.auth.home', '/dashboard').'?verified=1');
    }
}
```

- [ ] **Step 6: Create verification notification controller**

Create `src/Auth/Controllers/EmailVerificationNotificationController.php`:

```php
<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EmailVerificationNotificationController
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user((string) config('oidc.auth.guard', 'web'));

        if (! is_object($user) || ! method_exists($user, 'hasVerifiedEmail') || ! method_exists($user, 'sendEmailVerificationNotification')) {
            throw new HttpException(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
        }

        $user->sendEmailVerificationNotification();

        if ($request->wantsJson()) {
            return new JsonResponse('', 202);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
```

- [ ] **Step 7: Run task verification**

Run: `vendor/bin/pest tests/Auth/EmailVerificationFlowTest.php`

Expected: PASS.

Run: `vendor/bin/pint --test`

Expected: PASS.

Run: `vendor/bin/phpstan analyse`

Expected: PASS with zero errors.

- [ ] **Step 8: Commit**

```bash
git add routes/auth.php src/Auth/Controllers/EmailVerificationPromptController.php src/Auth/Controllers/VerifyEmailController.php src/Auth/Controllers/EmailVerificationNotificationController.php tests/Auth/EmailVerificationFlowTest.php
git commit -m "feat(auth): own email verification flow"
```

---

### Task 5: Account Flow Full Verification

**Files:**
- Test: `tests/Auth`
- Test: full package suite

**Interfaces:**
- Consumes: account routes, view seams, and action seams from Tasks 1-4.
- Produces: no new runtime interface.

- [ ] **Step 1: Run account-flow tests**

Run: `vendor/bin/pest tests/Auth`

Expected: PASS for `AuthViewSeamsTest`, `RegistrationFlowTest`, `PasswordResetFlowTest`, and `EmailVerificationFlowTest`.

- [ ] **Step 2: Run the full package suite**

Run: `vendor/bin/pest`

Expected: PASS.

- [ ] **Step 3: Run style and static analysis gates**

Run: `vendor/bin/pint --test`

Expected: PASS.

Run: `vendor/bin/phpstan analyse`

Expected: PASS with zero errors.

- [ ] **Step 4: Commit any final cleanup**

```bash
git status --short
git commit -m "test(auth): verify package account flows"
```

Expected: if there are no final cleanup changes, skip the commit.

---

## Self-Review

- Spec coverage: covers package-owned view/action seams, account routes/controllers, registration, password reset, email verification, events, session regeneration, redirects, JSON response shapes, Testbench-only tests, and gates.
- Deliberate split: login, logout, password confirmation, login throttling, TOTP 2FA, recovery codes, passkeys, `pragmarx/google2fa`, `bacon/bacon-qr-code`, and `laravel/passkeys` remain for the separate "Phase 1a Login + 2FA" plan.
- Placeholder scan: no `TBD`, incomplete file paths, or undefined task-owned interfaces remain.
- Type consistency: `AuthViewManager`, `UserActionManager`, `OidcManager`, controller names, route names, and facade method names match across tasks.
