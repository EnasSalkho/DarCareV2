<?php

namespace App\Http\Middleware;

use App\Modules\Users\Models\User;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $actor->isAdmin()) {
            return $this->error('Forbidden. Admin access required.', null, 403);
        }

        return $next($request);
    }
}
