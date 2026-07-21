<?php
declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The server package deliberately leaves `login`/`logout` route
        // ownership to the relying party (see Handler's docblock), so the
        // workbench stands in for that host application here — exactly what
        // VerifyEmailPage's log-out link needs to resolve in tests.
        Route::post('/logout', fn () => redirect('/'))->name('logout');
    }
}
