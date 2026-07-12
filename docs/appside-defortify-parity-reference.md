# De-Fortify 1a-① — account flows (registration, password reset, email verification)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Replace Fortify's registration, password-reset, and email-verification controllers with app-owned controllers over Laravel core — **no behavior change** — as the first slice of removing Fortify.

**Architecture:** Disable each Fortify *feature* (so Fortify stops registering its routes), then register app-owned routes under the *same route names* pointing at owned controllers that reproduce Fortify's exact behavior (events, session regeneration, redirect/`back()`/JSON response shapes). The existing Lattice pages and the existing auth test suite are the parity oracle and stay unchanged (except removing one Fortify skip-guard). Fortify stays installed for login/2FA/passkeys/password-confirmation — those are the next plan (1a-②).

**Tech Stack:** Laravel 13 (Auth, `Password` broker, `MustVerifyEmail`, events), Pest. No new dependencies.

## Global Constraints

- App-only changes under `app/` + `routes/` + `config/fortify.php` + `bootstrap/`. No package (`packages/laravel-oidc`) changes. No `composer` dependency changes in this plan (Fortify stays installed until 1a-②).
- **Parity, not new behavior:** the existing tests in `tests/Feature/Auth/{RegistrationTest,PasswordResetTest,EmailVerificationTest,VerificationNotificationTest}.php` must pass unchanged, except removing the `skipUnlessFortifyHas(...)` guard where noted so the test actually runs.
- Owned controllers live in `app/Auth/Http/Controllers/`; owned routes in a new `routes/auth.php`.
- PHP: `<?php` then `declare(strict_types=1);`. Follow existing app conventions (constructor property promotion, typed signatures, no narration comments — CLAUDE.md).
- Redirect/response targets copied verbatim from the current behavior (documented per task).
- Gate before every commit: `vendor/bin/pint --dirty` (fix), `php artisan test --compact tests/Feature/Auth`, and `vendor/bin/phpstan analyse` (zero errors).
- Conventional-commit subjects, no agent attribution / `Co-Authored-By`.

---

### Task 1: Owned routes file + owned registration

**Files:**
- Create: `routes/auth.php`
- Create: `app/Auth/Http/Controllers/RegisteredUserController.php`
- Modify: `bootstrap/app.php` (load `routes/auth.php`)
- Modify: `config/fortify.php` (remove `Features::registration()`)
- Test (parity, exists): `tests/Feature/Auth/RegistrationTest.php`

**Interfaces:**
- Produces: routes `register` (GET, name `register`) and `register.store` (POST, name `register.store`); `routes/auth.php` loaded under `web` middleware. Later tasks add their routes to `routes/auth.php`.

- [ ] **Step 1: Confirm the parity test passes today (Fortify)**

Run: `php artisan test --compact tests/Feature/Auth/RegistrationTest.php`
Expected: PASS (Fortify handles it now).

- [ ] **Step 2: Create the owned routes file with the registration routes**

`routes/auth.php`:

```php
<?php

declare(strict_types=1);

use App\Auth\Http\Controllers\RegisteredUserController;
use App\Auth\Pages\RegisterPage;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', fn () => new RegisterPage)->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
});
```

Executor note: the GET view route returns the Lattice `RegisterPage` instance exactly as Fortify's `Fortify::registerView(fn () => new RegisterPage)` did — the Page is renderable as a response. If returning the instance directly does not render, wrap as Fortify did (call the same view closure); confirm by loading `GET /register` in the app.

- [ ] **Step 3: Load `routes/auth.php` in `bootstrap/app.php`**

Read the current `->withRouting(...)` call in `bootstrap/app.php`. Add a `then:` closure (or extend the existing one) that loads the auth routes under `web`:

```php
->withRouting(
    // ...existing web:/api:/commands:/health: args unchanged...
    then: function () {
        Route::middleware('web')->group(base_path('routes/auth.php'));
    },
)
```

Import `Illuminate\Support\Facades\Route` at the top of `bootstrap/app.php` if not present. If a `then:` closure already exists, append the group line inside it.

- [ ] **Step 4: Write the owned controller**

`app/Auth/Http/Controllers/RegisteredUserController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\Auth\Actions\CreateNewUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController
{
    public function store(Request $request, CreateNewUser $creator): JsonResponse|RedirectResponse
    {
        event(new Registered($user = $creator->create($request->all())));

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return redirect()->intended(route('dashboard.home'));
    }
}
```

Executor notes:
- `CreateNewUser::create(array $input): User` already exists and validates (`name`, `email` unique, `password` confirmed) + provisions a personal project. Do not duplicate its validation.
- This reproduces Fortify's `RegisteredUserController::store` exactly: fire `Registered`, log in on `web`, regenerate the session, JSON 201 or `redirect()->intended(home)`. The test asserts `assertRedirect(route('dashboard.home'))`, which `redirect()->intended(route('dashboard.home'))` satisfies when no intended URL is set.

- [ ] **Step 5: Disable Fortify's registration feature**

In `config/fortify.php`, remove the `Features::registration()` line from the `features` array (so Fortify no longer registers `register`/`register.store` — our routes own those names). Leave all other features.

Executor note: before disabling, grep the app for feature-gated UI — `rg "Features::enabled|Features::registration|Features::canManage" app/` — because a page may hide a link/section when a feature is off. If any UI conditions on `Features::registration()` (e.g. a "Sign up" link on `LoginPage`), replace that condition with an app-level check (a `config('auth.registration', true)` flag or an unconditional link) so removing the Fortify feature doesn't hide working UI. The owned `register` route still exists, so `route('register')` links keep resolving.

- [ ] **Step 6: Run the parity test**

Run: `php artisan test --compact tests/Feature/Auth/RegistrationTest.php`
Expected: PASS against the owned controller (new user registered, authenticated, redirected to `dashboard.home`).

- [ ] **Step 7: Full auth suite + gates + commit**

```bash
php artisan test --compact tests/Feature/Auth
vendor/bin/pint --dirty && vendor/bin/phpstan analyse
git add routes/auth.php app/Auth/Http/Controllers/RegisteredUserController.php bootstrap/app.php config/fortify.php
git commit -m "refactor(auth): own the registration flow, drop Fortify registration feature"
```

Expected: whole `tests/Feature/Auth` suite green; pint clean; phpstan zero errors.

---

### Task 2: Owned password reset (forgot + reset)

**Files:**
- Create: `app/Auth/Http/Controllers/PasswordResetLinkController.php`
- Create: `app/Auth/Http/Controllers/NewPasswordController.php`
- Modify: `routes/auth.php` (add the four password routes)
- Modify: `config/fortify.php` (remove `Features::resetPasswords()`)
- Test (parity, exists): `tests/Feature/Auth/PasswordResetTest.php` (remove the skip-guard)

**Interfaces:**
- Consumes: `routes/auth.php` from Task 1.
- Produces: routes `password.request` (GET), `password.email` (POST), `password.reset` (GET), `password.update` (POST).

- [ ] **Step 1: Remove the Fortify skip-guard so the test actually runs**

In `tests/Feature/Auth/PasswordResetTest.php`, delete the `beforeEach(function () { skipUnlessFortifyHas(Features::resetPasswords()); })` guard (and its now-unused imports). The three tests must run unconditionally: "reset password link can be requested", "password can be reset with valid token", "password cannot be reset with invalid token".

Run: `php artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: PASS (still on Fortify, now unguarded).

- [ ] **Step 2: Add the routes**

Append to `routes/auth.php` (inside the existing `guest` group is fine; keep imports at top):

```php
use App\Auth\Http\Controllers\NewPasswordController;
use App\Auth\Http\Controllers\PasswordResetLinkController;
use App\Auth\Pages\ForgotPasswordPage;
use App\Auth\Pages\ResetPasswordPage;

// inside the ->middleware('guest') group:
Route::get('forgot-password', fn () => new ForgotPasswordPage)->name('password.request');
Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::get('reset-password/{token}', fn (string $token) => new ResetPasswordPage)->name('password.reset');
Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
```

Executor note: `ResetPasswordPage` reads `token` from the route param and `email` from the query string itself (as it did under Fortify) — confirm it renders with the `{token}` param present.

- [ ] **Step 3: Write the link-request controller**

`app/Auth/Http/Controllers/PasswordResetLinkController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::broker(config('auth.defaults.passwords', 'users'))
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

Executor notes:
- Mirrors Fortify's `PasswordResetLinkController::store`: lowercase email (the app has `fortify.lowercase_usernames = true`), `sendResetLink`, success → `back()->with('status', ...)` (or JSON 200), failure → `back()->withInput()->withErrors(['email' => ...])` (or `ValidationException` for JSON). The broker name: Fortify used `config('fortify.passwords')` = `users`; `config('auth.defaults.passwords')` is `users` too. If `auth.defaults.passwords` is unset, hardcode `'users'` (the configured broker).
- `ForgotPasswordPage` shows the flashed `status` — unchanged.

- [ ] **Step 4: Write the reset controller**

`app/Auth/Http/Controllers/NewPasswordController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\Auth\Actions\ResetUserPassword;
use App\Auth\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewPasswordController
{
    public function __construct(private ResetUserPassword $resetUserPassword) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $status = Password::broker(config('auth.defaults.passwords', 'users'))->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $this->resetUserPassword->reset($user, $request->all());
                $user->setRememberToken(Str::random(60));
                event(new PasswordReset($user));
                Auth::guard('web')->login($user);
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

Executor notes:
- Reproduces Fortify's `NewPasswordController::store` + `CompletePasswordReset`: broker `reset` runs `ResetUserPassword::reset` (persists the new password) plus refresh remember-token, fire `PasswordReset`, log in on `web`; on success regenerate session and `redirect()->route('login')->with('status', ...)`. The test asserts `assertSessionHasNoErrors()->assertRedirect(route('login'))` on success and `assertSessionHasErrors('email')` on an invalid token.
- `ResetUserPassword::reset(User $user, array $input): void` already validates + saves the password.

- [ ] **Step 5: Disable Fortify's password-reset feature**

Remove `Features::resetPasswords()` from `config/fortify.php` features.

- [ ] **Step 6: Run parity + full suite + gates + commit**

```bash
php artisan test --compact tests/Feature/Auth/PasswordResetTest.php
php artisan test --compact tests/Feature/Auth
vendor/bin/pint --dirty && vendor/bin/phpstan analyse
git add routes/auth.php app/Auth/Http/Controllers/PasswordResetLinkController.php app/Auth/Http/Controllers/NewPasswordController.php config/fortify.php tests/Feature/Auth/PasswordResetTest.php
git commit -m "refactor(auth): own the password-reset flow, drop Fortify resetPasswords feature"
```

Expected: PasswordResetTest green (3 tests, unguarded); whole suite green; pint/phpstan clean.

---

### Task 3: Owned email verification (notice + verify + send)

**Files:**
- Create: `app/Auth/Http/Controllers/EmailVerificationPromptController.php`
- Create: `app/Auth/Http/Controllers/VerifyEmailController.php`
- Create: `app/Auth/Http/Controllers/EmailVerificationNotificationController.php`
- Modify: `routes/auth.php` (add the three verification routes)
- Modify: `config/fortify.php` (remove `Features::emailVerification()`)
- Test (parity, exists): `tests/Feature/Auth/EmailVerificationTest.php`, `tests/Feature/Auth/VerificationNotificationTest.php`

**Interfaces:**
- Consumes: `routes/auth.php`.
- Produces: routes `verification.notice` (GET), `verification.verify` (GET, signed), `verification.send` (POST).

- [ ] **Step 1: Confirm both parity tests pass today**

Run: `php artisan test --compact tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Auth/VerificationNotificationTest.php`
Expected: PASS (Fortify).

- [ ] **Step 2: Add the routes**

Append to `routes/auth.php` (verification routes require `auth`, not `guest`; add a separate group):

```php
use App\Auth\Http\Controllers\EmailVerificationNotificationController;
use App\Auth\Http\Controllers\EmailVerificationPromptController;
use App\Auth\Http\Controllers\VerifyEmailController;

Route::middleware('auth')->group(function () {
    Route::get('email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')->name('verification.send');
});
```

- [ ] **Step 3: Write the three controllers**

`app/Auth/Http/Controllers/EmailVerificationPromptController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\Auth\Pages\VerifyEmailPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController
{
    public function __invoke(Request $request): VerifyEmailPage|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended('/dashboard')
            : new VerifyEmailPage;
    }
}
```

`app/Auth/Http/Controllers/VerifyEmailController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended('/dashboard?verified=1');
    }
}
```

`app/Auth/Http/Controllers/EmailVerificationNotificationController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        if ($request->wantsJson()) {
            return new JsonResponse('', 202);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
```

Executor notes:
- `EmailVerificationRequest` (Laravel core) validates the signed `{id}/{hash}` and authorizes the user — same primitive Fortify's `VerifyEmailController` used. It requires the route params named `id` and `hash` (they are).
- Behavior mirrors Fortify exactly: notice → `intended('/dashboard')` if verified else the page; verify → mark + `Verified` event, `intended('/dashboard?verified=1')` (and no event if already verified); send → `back()->with('status','verification-link-sent')` (the value `VerifyEmailPage` checks) or `intended('/dashboard')` if already verified. `VerificationNotificationTest` asserts the sent case redirects to `route('home')` (= `/`, where `back()` lands with no referer) and the verified case to `/dashboard`.
- `VerifyEmailPage` shows its status only when the flash equals `'verification-link-sent'` — preserved.

- [ ] **Step 4: Disable Fortify's email-verification feature**

Remove `Features::emailVerification()` from `config/fortify.php` features.

- [ ] **Step 5: Run parity + full suite + gates + commit**

```bash
php artisan test --compact tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Auth/VerificationNotificationTest.php
php artisan test --compact tests/Feature/Auth
vendor/bin/pint --dirty && vendor/bin/phpstan analyse
git add routes/auth.php app/Auth/Http/Controllers/EmailVerification*Controller.php app/Auth/Http/Controllers/VerifyEmailController.php config/fortify.php
git commit -m "refactor(auth): own the email-verification flow, drop Fortify emailVerification feature"
```

Expected: both verification test files green; whole suite green; pint/phpstan clean.

---

### Task 4: Cleanup + full verification

**Files:**
- Modify: `app/Auth/Providers/FortifyServiceProvider.php` (drop now-dead view/action bindings for the owned flows)

**Interfaces:** none new.

- [ ] **Step 1: Remove dead Fortify bindings for the owned flows**

In `app/Auth/Providers/FortifyServiceProvider.php::configureViews()`/`configureActions()`, remove the bindings that are now handled by owned controllers: `Fortify::registerView(...)`, `Fortify::requestPasswordResetLinkView(...)`, `Fortify::resetPasswordView(...)`, `Fortify::verifyEmailView(...)`, `Fortify::createUsersUsing(...)`, `Fortify::resetUserPasswordsUsing(...)`. **Keep** `loginView`, `twoFactorChallengeView`, `confirmPasswordView` (still Fortify in this plan). Keep all rate limiters.

Executor note: these bindings are inert once their features/routes are gone, but removing them keeps the provider honest and avoids confusion in 1a-②. Do not remove the whole provider.

- [ ] **Step 2: Full auth suite + broader smoke + gates**

```bash
php artisan test --compact tests/Feature/Auth
php artisan test --compact
vendor/bin/pint --test && vendor/bin/phpstan analyse
```

Expected: `tests/Feature/Auth` green; full app suite green (no regression from the owned flows); pint clean; phpstan zero errors. Manually confirm `GET /register`, `/forgot-password`, `/reset-password/{token}`, `/email/verify` still render their Lattice pages (the view routes return the Page instances).

- [ ] **Step 3: Commit**

```bash
git add app/Auth/Providers/FortifyServiceProvider.php
git commit -m "chore(auth): drop dead Fortify bindings for owned account flows"
```
