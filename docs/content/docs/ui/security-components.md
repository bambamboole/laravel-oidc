---
title: Security components
description: The Lattice action, form, field, fragment, and table IDs this package ships for settings pages, and how to compose them into your own.
---

Beyond the auth-flow pages, the package ships the Lattice building blocks a settings/profile
page needs to manage two-factor authentication, passkeys, and email verification. They are
discovered through the package's Lattice manifest (`extra.lattice.discover`, see
[Installation](/ui/installation/)) — compose them into your own page rather than rebuilding
the underlying logic.

## IDs

| Kind | ID | Class |
| --- | --- | --- |
| Form | `oidc.two-factor.setup` | `Forms\TwoFactorSetupForm` |
| Field | `field.oidc.two-factor-setup` | `Fields\TwoFactorSetupField` |
| Action | `oidc.two-factor.revoke-factor` | `Actions\RevokeFactorAction` |
| Action | `oidc.two-factor.regenerate-recovery-codes` | `Actions\RegenerateRecoveryCodesAction` |
| Action | `oidc.send-verification-email` | `Actions\SendVerificationEmailAction` |
| Fragment | `oidc.recovery-codes` | `Fragments\RecoveryCodesFragment` |
| Table | `oidc.two-factor.methods` | `Tables\TwoFactorMethodsTable` |

Every class lives under `Bambamboole\LaravelOidc\Ui\` (e.g. `Bambamboole\LaravelOidc\Ui\Forms\TwoFactorSetupForm`).

:::note
Enrolling used to be spread across `oidc.two-factor.enable`, `oidc.two-factor.confirm`, the
`oidc.two-factor-setup` fragment, and a standalone passkey-registration component. All four
are gone: `oidc.two-factor.setup` replaces them with a single wizard, and there is no
`oidc.two-factor.disable` any more — removing the last method *is* turning two-factor off.
:::

## The setup wizard

`oidc.two-factor.setup` is one form whose root is a Lattice `Wizard` with two steps:

1. **Method** — a `Choice` built from `FactorRegistry::enrollmentOptions()`. A provider may
   offer more than one way in; `webauthn` offers `passkey` and `security_key`, which differ
   only in the authenticator attachment the ceremony asks the browser for. A provider you
   register yourself appears here without touching this package.
2. **Set up** — the `field.oidc.two-factor-setup` field. It depends on the chosen option, so
   picking one fires Lattice's resolve sub-request: the server begins that enrollment and
   hands the payload back as the field's props (QR code and secret for a code-based factor,
   the WebAuthn creation options for a ceremony). Finishing submits the proof — a typed code
   or the credential the browser minted — in the form's single submit.

Beginning an enrollment from a resolve is a deliberate write on a read-shaped call. It is
idempotent per option: the TOTP provider reuses an existing unconfirmed factor, and webauthn
reuses the options already parked in the session unless a *different* option is picked. That
matters because the credential the browser produces is bound to the challenge it was shown.

Label, description, icon, and the "good for" badge come from
`Support\EnrollmentOptionLabels`, which reads `oidc-ui::security.option.{id}.*` and falls
back to the option id — so a host-registered provider renders sensibly before it ships
translations.

## Behavior worth knowing before composing

- **`oidc.two-factor.methods`** lists every confirmed, non-backup enrollment across all
  registered providers — passkeys included, named by their authenticator — plus a row for
  the recovery codes backing them (`n of m left`). Factor rows carry
  `oidc.two-factor.revoke-factor` (context: `provider` + `enrollment`); the backup row
  carries `oidc.two-factor.regenerate-recovery-codes`.
- **Turning two-factor off** is revoking the last challengeable enrollment. `EnrollmentPolicy`
  clears the recovery codes at that point, so the backup never outlives what it backs up.
- **`oidc.recovery-codes`** renders the unused recovery codes (copyable). It is opened
  automatically — once, when confirming the first factor backfills codes, and by
  `oidc.two-factor.regenerate-recovery-codes` — so compose a `Modal` with that id. Both ids
  are context-overridable (`recovery_codes_modal` on the setup form, `modal` on the
  regenerate action).
- **`oidc.send-verification-email`** is a no-op toast (`already-verified`) when the user's
  email is already verified.

## Composing them into a settings page

```php
use Bambamboole\LaravelOidc\Ui\Forms\TwoFactorSetupForm;
use Bambamboole\LaravelOidc\Ui\Fragments\RecoveryCodesFragment;
use Bambamboole\LaravelOidc\Ui\Tables\TwoFactorMethodsTable;
use Lattice\Form\Components\Form;
use Lattice\Fragments\Components\Fragment;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Button;
use Lattice\Ui\Components\Modal;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Effects\Builtin\OpenModal;

Stack::make('two-factor')->schema([
    Table::lazy(TwoFactorMethodsTable::class),
    Button::make('Add method')->effects(new OpenModal('oidc.two-factor-setup')),
]);

Modal::make('oidc.two-factor-setup')
    ->schema([Form::use(TwoFactorSetupForm::class)]);

Modal::make('oidc.recovery-codes')
    ->schema([Fragment::lazy(RecoveryCodesFragment::class)]);
```

The setup modal's id is yours to choose — the button opens it — but `oidc.recovery-codes`
must match what the form opens, or pass your own id as the form's `recovery_codes_modal`
context.
