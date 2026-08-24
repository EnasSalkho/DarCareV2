<?php
// app/Exceptions/Handler.php

namespace App\Exceptions;

use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;

    public function render($request, Throwable $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {

            if ($e instanceof ValidationException) {
                return $this->error('Validation failed', $e->errors(), 422);
            }

            if ($e instanceof AuthenticationException) {
                return $this->error('Unauthenticated', null, 401);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return $this->error('Resource not found', null, 404);
            }

            return $this->error($e->getMessage(), null, 500);
        }

        return parent::render($request, $e);
    }
}
