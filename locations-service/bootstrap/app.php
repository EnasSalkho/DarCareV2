<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Modules\Cities\Http\Middleware\SetAppLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Modules\Locations\Providers\LocationsServiceProvider::class,
        App\Modules\Cities\Providers\CitiesServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetAppLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
