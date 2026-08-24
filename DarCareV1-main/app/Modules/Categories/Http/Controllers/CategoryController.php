<?php
// app/Modules/Categories/Http/Controllers/CategoryController.php

namespace App\Modules\Categories\Http\Controllers;

use App\Modules\Categories\Contracts\CategoryServiceInterface;
use App\Modules\Categories\Http\Resources\CategoryResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly CategoryServiceInterface $categoryService) {}

    public function index(): JsonResponse
    {
        return $this->success(CategoryResource::collection($this->categoryService->all()));
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new CategoryResource($this->categoryService->find($id)));
    }
}
