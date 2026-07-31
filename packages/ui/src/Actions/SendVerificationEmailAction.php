<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Actions;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Lattice\Lattice\Actions\ActionDefinition;
use Lattice\Lattice\Actions\ActionResult;
use Lattice\Lattice\Actions\Components\Action as ActionComponent;
use Lattice\Lattice\Attributes\AsAction;
use Lattice\Lattice\Ui\Enums\Emphasis;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\Variant;

#[AsAction('oidc.send-verification-email')]
class SendVerificationEmailAction extends ActionDefinition
{
    public function definition(ActionComponent $action): ActionComponent
    {
        return $action
            ->label(__('oidc-ui::security.resend-verification'))
            ->method(HttpMethod::Post)
            ->emphasis(Emphasis::Link);
    }

    public function handle(Request $request): ActionResult
    {
        $user = auth()->user();

        abort_unless($user instanceof MustVerifyEmail, 403);

        if ($user->hasVerifiedEmail()) {
            return ActionResult::success()->toast(__('oidc-ui::security.already-verified'), Variant::Info);
        }

        $user->sendEmailVerificationNotification();

        return ActionResult::success()->toast(__('oidc-ui::security.verification-sent'), Variant::Success);
    }
}
