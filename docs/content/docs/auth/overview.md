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
by the `users` provider, and points `passport.guard` at it so the OIDC authorization flow and
the auth engine share one guard.

## View seams

Bind each view in a service provider's `boot()`. Each closure receives the `Request` and returns
a renderable response (typically a `view(...)`, an Inertia response, or JSON).

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

Oidc::loginView(fn ($request) => view('auth.login'));
Oidc::registerView(fn ($request) => view('auth.register'));
Oidc::requestPasswordResetLinkView(fn ($request) => view('auth.forgot-password'));
Oidc::resetPasswordView(fn ($request) => view('auth.reset-password'));
Oidc::verifyEmailView(fn ($request) => view('auth.verify-email'));
Oidc::confirmPasswordView(fn ($request) => view('auth.confirm-password'));
Oidc::twoFactorChallengeView(fn ($request) => view('auth.two-factor-challenge'));
```

A flow whose view is not bound throws a `RuntimeException` when that route is hit, so you bind
only the flows you enable.

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
