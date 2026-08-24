<?php
namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterProviderRequest;
use App\Modules\Auth\Http\Requests\RegisterUserRequest;
use App\Modules\Auth\Http\Resources\AuthResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AuthServiceInterface $authService) {}

    public function registerUser(RegisterUserRequest $request): JsonResponse
    {
        $result = $this->authService->registerUser($request);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
            ]
        ], 201);
    }

    public function registerProvider(RegisterProviderRequest $request): JsonResponse
    {
        $result = $this->authService->registerProvider($request);
        return $this->created(new AuthResource($result['provider'], $result['token']), 'Provider registered successfully');
    }

    public function loginUser(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request, 'user');
        return $this->success(new AuthResource($result['user'], $result['token']), 'Login successful');
    }

    public function loginProvider(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request, 'provider');
        return $this->success(new AuthResource($result['user'], $result['token']), 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['nullable', 'string', 'max:4096'],
        ]);

        $this->authService->logout(
            $request->user(),
            $validated['device_token'] ?? null
        );

        return $this->success(null, 'Logged out successfully');
    }
}