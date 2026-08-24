<?php
// app/Modules/Categories/Services/CategoryService.php

namespace App\Modules\Categories\Services;

use App\Modules\Categories\Contracts\CategoryServiceInterface;
use App\Modules\Categories\Models\Category;
use Illuminate\Support\Str;

class CategoryService implements CategoryServiceInterface
{
    public function all(): mixed
    {
        return Category::where('is_active', true)->get();
    }

    public function find(int $id): object
    {
        return Category::findOrFail($id);
    }



    public function getAllCategoriesForAdmin()
    {
        return Category::withCount('providers')->latest()->get(); // جلب التصنيفات مع عدّ كم حرفي بداخل كل تصنيف!
    }

    public function createCategory(array $data)
    {
        // توليد الـ slug تلقائياً من الاسم المكتوب قبل الحفظ بأمان
        $data['slug'] = Str::slug($data['name']) ?: time();
        return Category::create($data);
    }

    public function updateCategory(int $id, array $data)
    {
        $category = Category::find($id);
       if ($category) {
            if (isset($data['name'])) {
                $data['slug'] = Str::slug($data['name']) ?: time();
            }
            $category->update($data);
            return $category;
        }
        return null;
    }

    public function deleteCategory(int $id): bool
    {
        $category = Category::find($id);
        if ($category) {
            return $category->delete();
        }
        return false;
    }
}
