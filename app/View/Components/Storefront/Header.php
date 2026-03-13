<?php

namespace App\View\Components\Storefront;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Header extends Component
{
    public function render(): View
    {
        return view('components.storefront.header', [
            'categoryGroups' => $this->categoryGroups(),
            'searchTerm' => $this->searchTerm(),
        ]);
    }

    /**
     * @return Collection<int, array{parent: Category, children: Collection<int, Category>, is_top_level: bool}>
     */
    protected function categoryGroups(): Collection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $subcategories = Subcategory::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

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

    protected function searchTerm(): string
    {
        return trim((string) request()->query('q', ''));
    }
}
