---
title: Security components
description: The Lattice action, form, fragment, and table IDs this package ships for settings pages, and how to compose them into your own.
---

Beyond the auth-flow pages, the package ships the Lattice building blocks a settings/profile
page needs to manage two-factor authentication, passkeys, and email verification. They are
discovered through the package's Lattice manifest (`extra.lattice.discover`, see
[Installation](/ui/installation/)) — compose them into your own page rather than rebuilding
the underlying logic.

## IDs

| Kind | ID | Class |
| --- | --- | --- |
| Action | `oidc.two-factor.enable` | `Actions\EnableTwoFactorAuthenticationAction` |
| Action | `oidc.two-factor.disable` | `Actions\DisableTwoFactorAuthenticationAction` |
| Action | `oidc.two-factor.revoke-factor` | `Actions\RevokeFactorAction` |
| Action | `oidc.two-factor.regenerate-recovery-codes` | `Actions\RegenerateRecoveryCodesAction` |
| Action | `oidc.send-verification-email` | `Actions\SendVerificationEmailAction` |
| Form | `oidc.two-factor.confirm` | `Forms\ConfirmTwoFactorForm` |
| Fragment | `oidc.two-factor-setup` | `Fragments\TwoFactorSetupFragment` |
| Fragment | `oidc.recovery-codes` | `Fragments\RecoveryCodesFragment` |
| Table | `oidc.two-factor.methods` | `Tables\TwoFactorMethodsTable` |

The former `oidc.passkeys` table and `oidc.passkeys.delete` action have been removed —
passkey rows live in `oidc.two-factor.methods` and are revoked through
`oidc.two-factor.revoke-factor` like every other method.

Every class lives under `Bambamboole\LaravelOidc\Ui\` (e.g. `Bambamboole\LaravelOidc\Ui\Actions\EnableTwoFactorAuthenticationAction`).

## Behavior worth knowing before composing

- **Provider context.** `oidc.two-factor.enable`, `oidc.two-factor.confirm`, and
  `oidc.two-factor-setup` all accept a `provider` context key naming any
  `EnrollableFactorProvider` and default to `totp`, so existing compositions keep working
  unchanged. A non-enrollable or unknown provider returns `404`. The enable action's label
  is provider-aware ("Add authenticator app", "Add passkey", ...).
- **`oidc.two-factor.enable`** starts enrollment for the context provider and returns
  `ActionResult::openModal(...)` — by default `oidc.two-factor-setup`, overridable with a
  `modal` context key (a second provider needs its own modal). Your settings page needs a
  `Modal` with the matching id containing the setup fragment (see the example below).
- **`oidc.two-factor.disable`** turns two-factor off: it revokes every enrollment of every
  enrollable provider *except* passkeys (they double as a first-factor sign-in method and
  are removed individually). It and **`oidc.two-factor.regenerate-recovery-codes`** render
  a confirmation dialog before running.
- **`oidc.two-factor.methods`** lists every confirmed, non-backup enrollment across all
  registered providers — passkeys included, with their authenticator name. Each row of an
  enrollable provider carries `oidc.two-factor.revoke-factor` (context: `provider` +
  `enrollment`).
- **`oidc.recovery-codes`** renders the unused recovery codes (copyable). It is opened
  automatically — after the first factor confirmation backfills codes, and by
  `oidc.two-factor.regenerate-recovery-codes` — so compose a `Modal` with that id on your
  settings page. Both modal ids are context-overridable (`recovery_codes_modal` on the
  confirm form, `modal` on the regenerate action).
- **`oidc.send-verification-email`** is a no-op toast (`already-verified`) when the user's email
  is already verified.
- **`oidc.two-factor-setup`** (the fragment) renders per provider: the QR code + secret +
  the `oidc.two-factor.confirm` form for a pending TOTP enrollment (or "already enabled"
  once confirmed), the passkey registration ceremony for `webauthn`, and for any other
  enrollable provider the pending enrollment's label, its scalar setup metadata, and the
  confirm form — override the fragment (or compose your own) for richer provider-specific
  setup UI.

## Composing them into a settings page

```php
use Bambamboole\LaravelOidc\Ui\Actions\DisableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\EnableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\RegenerateRecoveryCodesAction;
use Bambamboole\LaravelOidc\Ui\Fragments\RecoveryCodesFragment;
use Bambamboole\LaravelOidc\Ui\Fragments\TwoFactorSetupFragment;
use Bambamboole\LaravelOidc\Ui\Tables\TwoFactorMethodsTable;
use Lattice\Lattice\Actions\Components\Action;
use Lattice\Lattice\Fragments\Components\Fragment;
use Lattice\Lattice\Tables\Components\Table;
use Lattice\Lattice\Ui\Components\Modal;
use Lattice\Lattice\Ui\Components\Stack;

Stack::make('two-factor')->schema([
    Action::use(EnableTwoFactorAuthenticationAction::class),                       // "Add authenticator app"
    Action::use(EnableTwoFactorAuthenticationAction::class, ['provider' => 'webauthn']), // "Add passkey"
    Action::use(DisableTwoFactorAuthenticationAction::class)->visible($twoFactorEnabled),
    Action::use(RegenerateRecoveryCodesAction::class)->visible($twoFactorEnabled),
    Table::lazy(TwoFactorMethodsTable::class),
]);

// The modal ids must match what the actions open ("oidc.two-factor-setup" and
// "oidc.recovery-codes"), not the fragments' own ids.
Modal::make('oidc.two-factor-setup')
    ->schema([Fragment::lazy(TwoFactorSetupFragment::class)]);

Modal::make('oidc.recovery-codes')
    ->schema([Fragment::lazy(RecoveryCodesFragment::class)]);
```

To offer a second setup modal for passkeys, pass a `modal` context to the enable action and
compose a modal with that id containing
`Fragment::lazy(TwoFactorSetupFragment::class, ['provider' => 'webauthn'])`.
