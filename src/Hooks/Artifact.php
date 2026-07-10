<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks;

enum Artifact
{
    case IdToken;
    case AccessToken;
    case Userinfo;
}
