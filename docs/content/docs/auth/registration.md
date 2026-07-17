---
title: Registration
description: The registration flow, the createUsersUsing action seam, and where validation lives.
---

Registration is owned by `RegisteredUserController`. Your app fills the view seam with
`Oidc::registerView(...)` and the action seam with `Oidc::createUsersUsing(...)`; the package owns
the event dispatch, sign-in, and session regeneration.

## Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.register` | `GET` | `auth/register` | `web`, `guest:identity` |
| `identity.register.store` | `POST` | `auth/register` | `web`, `guest:identity` |

`GET identity.register` renders your bound `registerView`. If no view is bound, hitting the route
throws a `RuntimeException`.

## The registration flow (`POST identity.register.store`)

1. The full request input is collected, with `email` **lowercased**.
2. The [`createUsersUsing`](/auth/overview/) action is invoked with that input array and must
   return the created `Authenticatable`.
3. Laravel's `Illuminate\Auth\Events\Registered` event is fired for the new user (this is what
   triggers the [email verification](/auth/email-verification/) notification when your user model
   implements `MustVerifyEmail`).
4. The user is logged in on the `identity` guard.
5. The **session is regenerated**.

### Response

- A JSON request (`wantsJson`) receives an empty **`201`** response.
- A browser request is redirected via `redirect()->intended(...)` to `config('oidc.auth.home')`
  (default `/dashboard`).

## Validation lives in your action

The controller does **not** validate the registration input beyond lowercasing `email`. Ownership
of the rules is yours — enforce them inside your `createUsersUsing` action (or a form request that
feeds it), exactly where your app's persistence and password hashing already live. This keeps the
package out of your user model's shape:

```php
use Illuminate\Support\Facades\Validator;

class CreateNewUser
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
```

Register it in a service provider `boot()`:

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

Oidc::createUsersUsing(App\Actions\CreateNewUser::class);
```

Facade registration in `boot()` is keyless-safe: it never resolves the
encrypter, so `package:discover` and other artisan runs work before an
`APP_KEY` exists.

## After registration

When your user model implements `MustVerifyEmail`, the `Registered` event sends the verification
notification and the user is redirected/gated through the
[email verification](/auth/email-verification/) flow before reaching protected pages.
