<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Controllers;

use Bambamboole\LaravelOidc\Auth\AuthViewManager;
use Illuminate\Http\Request;

class NewPasswordController
{
    public function __construct(private readonly AuthViewManager $views) {}

    public function create(Request $request): mixed
    {
        return $this->views->render(AuthViewManager::ResetPassword, $request);
    }
}
