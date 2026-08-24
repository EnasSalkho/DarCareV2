<?php

namespace App\Modules\Users\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Users\Contracts\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // حقن خدمة المستخدمين
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {}

    /**
     * عرض قائمة الزبائن للأدمن مع ميزة البحث
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->getAllUsersForAdmin(
            $request->query('search')
        );

        return response()->json([
            'status' => 'success',
            'data' => $users
        ], 200);
    }

    /**
     * حذف زبون من السيستم (Soft Delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $this->userService->deleteUserForAdmin($id);

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف حساب المستخدم بنجاح وضعه في سلة المهملات'
        ], 200);
    }
}
