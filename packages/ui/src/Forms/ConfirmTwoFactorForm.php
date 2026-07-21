<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Forms;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lattice\Lattice\Attributes\AsForm;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\OtpInput;
use Lattice\Lattice\Forms\FormDefinition;
use Lattice\Lattice\Http\LatticeResponse;
use Lattice\Lattice\Ui\Enums\Variant;

#[AsForm('oidc.two-factor.confirm')]
class ConfirmTwoFactorForm extends FormDefinition
{
    use ManagesTwoFactor;

    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function definition(Form $form, Request $request): Form
    {
        return $form
            ->schema([
                OtpInput::make('code', __('oidc-ui::security.two-factor.code'))
                    ->length(6)
                    ->helperText(__('oidc-ui::security.two-factor.code-help'))
                    ->rules(['required', 'string']),
            ])
            ->submitLabel(__('oidc-ui::security.two-factor.confirm'));
    }

    public function handle(Request $request): LatticeResponse
    {
        $user = $this->twoFactorUser();

        if (! $this->twoFactor->confirm($user, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('oidc-ui::security.two-factor.invalid-code'),
            ]);
        }

        return $this->toast(__('oidc-ui::security.two-factor.enabled-toast'), Variant::Success)->back();
    }
}
