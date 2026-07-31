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
| Action | `oidc.passkeys.delete` | `Actions\DeletePasskeyAction` |
| Action | `oidc.send-verification-email` | `Actions\SendVerificationEmailAction` |
| Form | `oidc.two-factor.confirm` | `Forms\ConfirmTwoFactorForm` |
| Fragment | `oidc.two-factor-setup` | `Fragments\TwoFactorSetupFragment` |
| Table | `oidc.two-factor.methods` | `Tables\TwoFactorMethodsTable` |
| Table | `oidc.passkeys` | `Tables\PasskeysTable` |

Every class lives under `Bambamboole\LaravelOidc\Ui\` (e.g. `Bambamboole\LaravelOidc\Ui\Actions\EnableTwoFactorAuthenticationAction`).

## Behavior worth knowing before composing

- **Provider context.** `oidc.two-factor.enable`, `oidc.two-factor.disable`,
  `oidc.two-factor.confirm`, and `oidc.two-factor-setup` all accept a `provider` context key
  naming any `EnrollableFactorProvider` and default to `totp`, so existing compositions keep
  working unchanged. A non-enrollable or unknown provider returns `404`.
- **`oidc.two-factor.enable`** starts enrollment for the context provider and returns
  `ActionResult::openModal(...)` — by default `oidc.two-factor-setup`, overridable with a
  `modal` context key (a second provider needs its own modal). Your settings page needs a
  `Modal` with the matching id containing the setup fragment (see the example below).
- **`oidc.two-factor.disable`** revokes every enrollment of the context provider;
  it and **`oidc.two-factor.regenerate-recovery-codes`** render a confirmation dialog before
  running, then reload the page.
- **`oidc.two-factor.methods`** lists every confirmed, non-backup enrollment across all
  registered providers. Rows of enrollable providers carry `oidc.two-factor.revoke-factor`
  (context: `provider` + `enrollment`); passkey rows are listed but managed through the
  richer `oidc.passkeys` table.
- **`oidc.passkeys.delete`** authorizes against `$request->context('passkey')`, i.e. it is meant
  to be attached as a row action on a passkeys table (it is already wired that way on
  `oidc.passkeys`), and reloads that table's component on success.
- **`oidc.send-verification-email`** is a no-op toast (`already-verified`) when the user's email
  is already verified.
- **`oidc.two-factor-setup`** (the fragment) renders the QR code + secret + the
  `oidc.two-factor.confirm` form while a TOTP enrollment is pending, or a plain "already
  enabled" message once it is confirmed. For another enrollable provider it renders the
  pending enrollment's label plus the confirm form — override the fragment (or compose your
  own) for provider-specific setup UI.

## Composing them into a settings page

```php
use Bambamboole\LaravelOidc\Ui\Actions\DisableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\EnableTwoFactorAuthenticationAction;
use Bambamboole\LaravelOidc\Ui\Actions\RegenerateRecoveryCodesAction;
use Bambamboole\LaravelOidc\Ui\Fragments\TwoFactorSetupFragment;
use Bambamboole\LaravelOidc\Ui\Tables\PasskeysTable;
use Bambamboole\LaravelOidc\Ui\Tables\TwoFactorMethodsTable;
use Lattice\Lattice\Actions\Components\Action;
use Lattice\Lattice\Fragments\Components\Fragment;
use Lattice\Lattice\Tables\Components\Table;
use Lattice\Lattice\Ui\Components\Modal;
use Lattice\Lattice\Ui\Components\Stack;

Stack::make('two-factor')->schema([
    Action::use(EnableTwoFactorAuthenticationAction::class)->visible(! $twoFactorEnabled),
    Action::use(DisableTwoFactorAuthenticationAction::class)->visible($twoFactorEnabled),
    Action::use(RegenerateRecoveryCodesAction::class)->visible($twoFactorEnabled),
    Table::lazy(TwoFactorMethodsTable::class),
]);

Stack::make('passkeys')->schema([
    Table::lazy(PasskeysTable::class),
]);

// The modal id must match the id EnableTwoFactorAuthenticationAction opens
// ("oidc.two-factor-setup"), not the fragment's own id.
Modal::make('oidc.two-factor-setup')
    ->schema([Fragment::lazy(TwoFactorSetupFragment::class)]);
```

`PasskeysTable`'s row actions already include `oidc.passkeys.delete` — nothing extra is needed
to make row deletion work. Add your own passkey-registration component (see
[Frontend setup](/ui/frontend-setup/)) next to the table to let users add new ones.
