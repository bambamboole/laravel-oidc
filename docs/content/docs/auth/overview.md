---
title: Auth engine overview & seams
description: How the package owns the authentication flows while your app fills the view and action seams.
---

Beyond the OIDC protocol layer, the package ships a **Fortify-equivalent auth engine**. All the
authentication *logic* lives in the package — login, registration, password reset, email
verification, password confirmation, and multi-factor. Your application fills two kinds of
**seams**: the views to render, and a couple of domain actions.

This mirrors how the OIDC consent flow already works: the package owns the flow, the app
provides the page and the user-facing decisions.

## The `identity` guard

The engine authenticates against a dedicated session guard, `identity` (configurable via
`oidc.auth.guard`). The package registers it automatically if your app hasn't defined it, backed
by the `users` provider, and routes the OIDC authorization flow through the same guard, so the
provider and the auth engine share one session.

## View seams

Each auth surface renders through a typed **view contract** — an interface under
`Bambamboole\LaravelOidc\Auth\Views` with a single `respond()` method that takes a matching
**prompt** (the page-specific data) and the `Request`, and returns a `Responsable` or `Response`:

```php
interface LoginView
{
    public function respond(LoginPrompt $prompt, Request $request): Responsable|Response;
}
```

Eight contracts cover every auth surface:

| Contract | Prompt | Renders for |
| --- | --- | --- |
| `LoginView` | `LoginPrompt` (`status`) | [Login](/auth/login/) |
| `RegisterView` | `RegisterPrompt` | [Registration](/auth/registration/) |
| `PasswordResetRequestView` | `PasswordResetRequestPrompt` (`status`) | [Password reset](/auth/passwords/) request step |
| `PasswordResetView` | `PasswordResetPrompt` (`token`, `email`, `status`) | [Password reset](/auth/passwords/) reset step |
| `EmailVerificationView` | `EmailVerificationPrompt` (`status`) | [Email verification](/auth/email-verification/) |
| `PasswordConfirmationView` | `PasswordConfirmationPrompt` | [Password confirmation](/auth/passwords/) |
| `TwoFactorChallengeView` | `TwoFactorChallengePrompt` | [Multi-factor challenge](/auth/multi-factor/) |
| `ConsentView` | `ConsentPrompt` (`client`, `user`, `scopes`, `authToken`) | [OAuth consent](/provider/endpoints/#consent-view-required) |

Override one by binding your implementation over the contract, typically in a service provider's
`boot()` (a bind there wins over the package's default, since package providers boot first):

```php
use Bambamboole\LaravelOidc\Auth\Views\LoginPrompt;
use Bambamboole\LaravelOidc\Auth\Views\LoginView;
use Illuminate\Http\Request;

$this->app->bind(LoginView::class, fn () => new class implements LoginView
{
    public function respond(LoginPrompt $prompt, Request $request)
    {
        return view('auth.login', ['status' => $prompt->status]);
    }
});
```

Controllers resolve the contract with `app(LoginView::class)->respond($prompt, $request)`, so a
bound class must be resolvable by the container with no arguments passed to `make()`; it receives
the real prompt only through `respond()`.

A flow whose contract is not bound throws `MissingAuthViewException` when that route is hit, so a
headless install fails loudly on the first request to an unbound surface instead of rendering
nothing. Install `bambamboole/laravel-oidc-ui` to bind all eight contracts at once (see
[UI installation](/ui/installation/)), or bind only the ones you enable yourself.

For engine tests that drive the real controllers without a view package installed, add
`Bambamboole\LaravelOidc\Testing\FakesAuthViews` to the test and call `fakeAuthViews()` before
hitting a `GET` route — it binds every contract to a minimal JSON responder (see
[Testing](/advanced/testing/)).

## Action seams

Two domain actions let the package stay out of your user model and persistence:

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

// Called by the registration flow with the validated input array.
// Return the created Authenticatable.
Oidc::createUsersUsing(App\Actions\CreateNewUser::class);

// Called by the password-reset flow with the user and validated input.
Oidc::resetUserPasswordsUsing(App\Actions\ResetUserPassword::class);
```

Each accepts a callable or an invokable/`class-string`. A `class-string` is resolved from the
container; a class is expected to expose a `create(array $input)` / `reset($user, array $input)`
method. `createUsersUsing` must return an `Authenticatable`.

## What the app keeps, what the package owns

- **The app keeps:** its auth views/pages, its `CreateNewUser` / `ResetUserPassword` domain
  actions, its `User` model, and its config values.
- **The package owns:** the controllers, routes, validation, event dispatch, session
  regeneration, rate limiting, the MFA machinery, and the post-login pipeline.

## The flows

- [Login & logout](/auth/login/)
- [Registration](/auth/registration/)
- [Password reset & confirmation](/auth/passwords/)
- [Email verification](/auth/email-verification/)
- [Multi-factor authentication](/auth/multi-factor/)
- [The post-login pipeline](/auth/post-login-pipeline/) — the adaptive decision hook and
  `acr`/`amr`.
