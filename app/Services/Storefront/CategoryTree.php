<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Collection;

class CategoryTree
{
    /**
     * @return Collection<int, array{parent: Category, children: Collection<int, Subcategory>, is_top_level: bool}>
     */
    public function groups(): Collection
    {
        $subcategories = Subcategory::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('category', function ($query): void {
                $query->where('is_active', true)->whereNull('deleted_at');
            })
            ->whereHas('products')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        $subcategoryCategoryIds = $subcategories
            ->pluck('category_id')
            ->unique()
            ->values();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($subcategoryCategoryIds): void {
                $query
                    ->whereHas('products')
                    ->orWhereIn('id', $subcategoryCategoryIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $childrenByParent = $subcategories->groupBy('category_id');

        return $categories
            ->map(function (Category $category) use ($childrenByParent): array {
                return [
                    'parent' => $category,
                    'children' => $childrenByParent->get($category->id, collect())->values(),
                    'is_top_level' => true,
                ];
            })
            ->values();
    }
}
