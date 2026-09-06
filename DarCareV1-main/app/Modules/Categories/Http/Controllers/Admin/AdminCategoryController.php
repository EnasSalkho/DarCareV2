<?php

namespace App\Modules\Categories\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Contracts\CategoryServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function __construct(
        private readonly CategoryServiceInterface $categoryService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->categoryService->getAllCategoriesForAdmin()
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // أضفنا unique لضمان عدم تكرار الاسم
            'name' => 'required|string|max:255|unique:categories,name',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $category = $this->categoryService->createCategory($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'تم إنشاء التصنيف بنجاح',
            'data' => $category
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            // أضفنا unique مع استثناء الـ ID الحالي عشان نقدر نعدل نفس القسم
            'name'  => 'sometimes|string|max:255|unique:categories,name,' . $id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $category = $this->categoryService->updateCategory($id, $request->all());
        
        if (!$category) {
            return response()->json(['status' => 'error', 'message' => 'التصنيف غير موجود'], 404);
        }
        return response()->json(['status' => 'success', 'message' => 'تم تحديث التصنيف بنجاح'], 200);
    }
    public function destroy(int $id): JsonResponse
    {
        if ($this->categoryService->deleteCategory($id)) {
            return response()->json(['status' => 'success', 'message' => 'تم حذف التصنيف بنجاح'], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'التصنيف غير موجود'], 404);
    }
}
