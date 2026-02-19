<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Products')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'b')]
    public string $brand = '';

    #[Url(as: 'c')]
    public string $category = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBrand(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function brands()
    {
        return Brand::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function subcategories()
    {
        return Category::query()->whereNotNull('parent_id')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['brand', 'subcategory'])
            ->withCount('variants')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->brand !== '', fn ($query) => $query->where('brand_id', (int) $this->brand))
            ->when($this->category !== '', fn ($query) => $query->where('subcategory_id', (int) $this->category))
            ->latest('updated_at')
            ->paginate(12);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Products')" :subheading="__('Read-only catalog projection from the main store')">
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search product')" placeholder="{{ __('Type product name...') }}" />

                <flux:select wire:model.live="brand" :label="__('Brand')">
                    <option value="">{{ __('All brands') }}</option>
                    @foreach ($this->brands as $brandOption)
                        <option value="{{ $brandOption->id }}">{{ $brandOption->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="category" :label="__('Subcategory')">
                    <option value="">{{ __('All subcategories') }}</option>
                    @foreach ($this->subcategories as $categoryOption)
                        <option value="{{ $categoryOption->id }}">{{ $categoryOption->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <flux:table :paginate="$this->products">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Brand') }}</flux:table.column>
                    <flux:table.column>{{ __('Subcategory') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Variants') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->products as $product)
                        <flux:table.row :key="$product->id">
                            <flux:table.cell variant="strong">{{ $product->name }}</flux:table.cell>
                            <flux:table.cell>{{ $product->brand?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $product->subcategory?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ str($product->status)->headline() }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($product->variants_count) }}</flux:table.cell>
                            <flux:table.cell>{{ $product->external_id ?? '-' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" :href="route('admin.products.show', $product)" wire:navigate>
                                    {{ __('View') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('No products found.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
