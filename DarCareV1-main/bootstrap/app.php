<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withProviders([
        App\Modules\Auth\Providers\AuthServiceProvider::class,
        App\Modules\Users\Providers\UsersServiceProvider::class,
        App\Modules\Providers\Providers\ProvidersServiceProvider::class,
        App\Modules\Categories\Providers\CategoriesServiceProvider::class,
        App\Modules\ServiceRequests\Providers\ServiceRequestsServiceProvider::class,
        App\Modules\Chat\Providers\ChatServiceProvider::class,
        App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
        App\Modules\Favorites\Providers\FavoritesServiceProvider::class,
        App\Modules\Ratings\Providers\RatingsServiceProvider::class,
        App\Modules\Dashboard\Providers\DashboardServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'data' => null,
                    'errors' => null,
                ], 429);
            }
        });
    })->create();
