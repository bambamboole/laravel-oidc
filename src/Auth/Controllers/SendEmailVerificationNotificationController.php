<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendEmailVerificationNotificationController
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user((string) config('oidc.auth.guard', 'web'));

        if (! $user instanceof MustVerifyEmail) {
            throw new HttpException(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended((string) config('oidc.auth.home', '/dashboard'));
        }

        $user->sendEmailVerificationNotification();

        if ($request->wantsJson()) {
            return new JsonResponse('', 202);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
