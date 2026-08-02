<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Http\Controllers;

use Bambamboole\LaravelOidc\Client\OidcClientManager;
use Bambamboole\LaravelOidc\Client\RelyingParty;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OidcLoginController
{
    public function __invoke(Request $request, RelyingParty $relyingParty, OidcClientManager $manager): Response
    {
        if ($manager->guard()->check()) {
            return $manager->redirectAfterLogin();
        }

        return $relyingParty->redirect($request);
    }
}
