<?php

namespace App\Providers;

use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'provider' => Provider::class,
        ]);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($this->actorKey($request));
        });

        RateLimiter::for('chat-send', function (Request $request) {
            return Limit::perMinute(30)->by($this->actorKey($request));
        });

        RateLimiter::for('chat-read', function (Request $request) {
            return Limit::perMinute(120)->by($this->actorKey($request));
        });

        RateLimiter::for('chat-open', function (Request $request) {
            return Limit::perMinute(20)->by($this->actorKey($request));
        });

        RateLimiter::for('device-tokens', function (Request $request) {
            return Limit::perMinute(20)->by($this->actorKey($request));
        });

        RateLimiter::for('admin-bulk-notifications', function (Request $request) {
            return Limit::perMinute(5)->by($this->actorKey($request));
        });
    }

    private function actorKey(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return 'ip:'.$request->ip();
        }

        $type = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : class_basename($user);

        return $type.':'.$user->getAuthIdentifier();
    }
}
