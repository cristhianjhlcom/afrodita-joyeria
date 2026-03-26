<?php

namespace App\View\Components\Storefront;

use App\Models\Category;
use App\Models\Subcategory;
use App\Services\Storefront\CategoryTree;
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
     * @return Collection<int, array{parent: Category, children: Collection<int, Subcategory>, is_top_level: bool}>
     */
    protected function categoryGroups(): Collection
    {
        return app(CategoryTree::class)->groups();
    }

    protected function searchTerm(): string
    {
        return trim((string) request()->query('q', ''));
    }
}
