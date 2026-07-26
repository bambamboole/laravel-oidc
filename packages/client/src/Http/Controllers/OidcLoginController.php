<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Http\Controllers;

use Bambamboole\LaravelOidc\Client\OidcClientManager;
use Bambamboole\LaravelOidc\Client\RelyingParty;
use Illuminate\Http\RedirectResponse;

class OidcLoginController
{
    public function __invoke(RelyingParty $relyingParty, OidcClientManager $manager): RedirectResponse
    {
        if ($manager->guard()->check()) {
            return $manager->redirectAfterLogin();
        }

        return $relyingParty->redirect();
    }
}
