<?php
// app/Modules/Users/Http/Controllers/UserController.php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Users\Contracts\UserServiceInterface;
use App\Modules\Users\Http\Requests\UpdateUserRequest;
use App\Modules\Users\Http\Resources\UserResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly UserServiceInterface $userService) {}

    public function profile(Request $request): JsonResponse
    {
        $user = $this->userService->getProfile($request->user()->id);
        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request): JsonResponse
    {
        $data = $request->safe()->except('profile_image');

        if ($request->hasFile('profile_image')) {
            $user = $this->userService->updateProfileImage($request->user()->id, $request->file('profile_image'));
        } else {
            $user = $this->userService->updateProfile($request->user()->id, $data);
        }

        return $this->success(new UserResource($user), 'Profile updated');
    }
}
