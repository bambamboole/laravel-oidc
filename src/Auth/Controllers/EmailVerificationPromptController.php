<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

class EmailVerificationPromptController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function __invoke(Request $request): mixed
    {
        $user = $request->user((string) config('oidc.auth.guard', 'identity'));

        if ($user instanceof MustVerifyEmail && $user->hasVerifiedEmail()) {
            return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
        }

        return $this->views->render(AuthViewManager::VerifyEmail, $request);
    }
}
