<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Controllers;

use Bambamboole\LaravelOidcClient\OidcClientManager;
use Bambamboole\LaravelOidcClient\RelyingParty;
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
