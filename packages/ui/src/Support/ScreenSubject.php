<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Support;

use Bambamboole\LaravelOidc\Server\Shared\Authentication\RequiredActionSubject;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who this package's screens are acting for.
 *
 * The default guard comes first: these components are built to be mounted on
 * the host application's own account page, behind its own guard. The same
 * screens are also reached mid-login — a realm that insists on a second factor
 * meets a user without one — where no session exists yet by design and only the
 * pending login names the user.
 *
 * Static because a custom field resolves against a clone of itself, with no
 * instance of the definition that owns it in reach.
 */
final class ScreenSubject
{
    /**
     * Factor providers build their own morph relations, so any Eloquent
     * authenticatable qualifies.
     */
    public static function current(): (Authenticatable&Model)|null
    {
        $user = auth()->user() ?? app(RequiredActionSubject::class)->current(request());

        return $user instanceof Model ? $user : null;
    }

    public static function currentOrFail(): Authenticatable&Model
    {
        return self::current() ?? abort(403);
    }
}
