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
            'categories' => $this->categories(),
            'searchTerm' => $this->searchTerm(),
        ]);
    }

    /**
     * @return Collection<int, Category>
     */
    protected function categories(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    protected function searchTerm(): string
    {
        return trim((string) request()->query('q', ''));
    }
}
