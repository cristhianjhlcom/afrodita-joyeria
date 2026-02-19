<?php

use App\Models\Category;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Categories')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->with(['parent'])
            ->withCount(['children', 'products'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(12);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Categories')" :subheading="__('Manage category hierarchy mirrored from the main store')">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search category')" placeholder="{{ __('Type a category name...') }}" />

            <flux:table :paginate="$this->categories">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Parent') }}</flux:table.column>
                    <flux:table.column>{{ __('Subcategories') }}</flux:table.column>
                    <flux:table.column>{{ __('Products') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->categories as $category)
                        <flux:table.row :key="$category->id">
                            <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                            <flux:table.cell>{{ $category->parent?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($category->children_count) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($category->products_count) }}</flux:table.cell>
                            <flux:table.cell>{{ $category->external_id ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __('No categories found.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
