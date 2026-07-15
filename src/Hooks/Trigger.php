<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks;

enum Trigger
{
    case ClientCredentials;
    case TokenExchange;
    case Userinfo;
}
