<?php

namespace App\View\Components\Storefront;

use App\Models\Category;
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
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        $parents = $categories
            ->whereNull('parent_id')
            ->values();
        $children = $categories
            ->whereNotNull('parent_id')
            ->values();
        $childrenByParent = $children->groupBy('parent_id');

        $groups = $parents->map(function (Category $parent) use ($childrenByParent): array {
            return [
                'parent' => $parent,
                'children' => $childrenByParent->get($parent->id, collect())->values(),
                'is_top_level' => true,
            ];
        });

        $orphanChildren = $children
            ->reject(fn (Category $child): bool => $parents->contains('id', $child->parent_id))
            ->values();

        $orphanGroups = $orphanChildren->map(function (Category $child): array {
            return [
                'parent' => $child,
                'children' => collect(),
                'is_top_level' => false,
            ];
        });

        return $groups
            ->concat($orphanGroups)
            ->sortBy(fn (array $group): string => $group['parent']->name)
            ->values();
    }

    protected function searchTerm(): string
    {
        return trim((string) request()->query('q', ''));
    }
}
