<?php
// app/Modules/Categories/Services/CategoryService.php

namespace App\Modules\Categories\Services;

use App\Modules\Categories\Contracts\CategoryServiceInterface;
use App\Modules\Categories\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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
        $data['slug'] = Str::slug($data['name']) ?: time();

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $data['image']->store('categories', 'public');
        } else {
            // تعيين مسار الصورة الافتراضية في حال عدم الرفع
            $data['image'] = 'categories/default.png'; 
        }

        return Category::create($data);
    }

    public function updateCategory(int $id, array $data)
    {
        $category = Category::find($id);
        
        if ($category) {
            if (isset($data['name'])) {
                $data['slug'] = Str::slug($data['name']) ?: time();
            }

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                // حذف الصورة القديمة إن وجدت
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }
                $data['image'] = $data['image']->store('categories', 'public');
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
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            return $category->delete();
        }
        return false;
    }
}
