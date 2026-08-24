<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Users\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Hash;

class AdminAuthController
{
    use ApiResponseTrait;

    public function adminLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = User::where('email', $request->email)->where('role', RoleEnum::Admin->value)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return $this->error('Invalid credentials or insufficient admin privileges.', null, 401);
        }

        $token = $admin->createToken('admin_token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'admin',
            ],
        ], 'Admin login successful');
    }
}
