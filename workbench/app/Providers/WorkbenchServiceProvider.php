<?php
declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::get('/login', fn () => response('', 200))->name('login');
    }
}
