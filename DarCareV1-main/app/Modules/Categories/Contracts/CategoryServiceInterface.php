<?php
// app/Modules/Categories/Contracts/CategoryServiceInterface.php

namespace App\Modules\Categories\Contracts;

interface CategoryServiceInterface
{
    public function all(): mixed;
    public function find(int $id): object;

    public function getAllCategoriesForAdmin();
    public function createCategory(array $data);
    public function updateCategory(int $id, array $data);
    public function deleteCategory(int $id): bool;
}
