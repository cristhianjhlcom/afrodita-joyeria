<?php

use App\Models\ProductVariant;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Inventory')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'low')]
    public bool $onlyLowStock = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyLowStock(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function variants()
    {
        return ProductVariant::query()
            ->with('product')
            ->when($this->search !== '', function ($query): void {
                $query
                    ->where('sku', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$this->search}%"));
            })
            ->when($this->onlyLowStock, fn ($query) => $query->where('stock_available', '<=', 5))
            ->latest('updated_at')
            ->paginate(15);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Inventory')" :subheading="__('Monitor variant stock levels synced from the main store')">
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search SKU / Product')" placeholder="{{ __('Type SKU, code, or product name...') }}" />
                <flux:checkbox wire:model.live="onlyLowStock" :label="__('Only low stock (<= 5 available)')" />
            </div>

            <flux:table :paginate="$this->variants">
                <flux:table.columns>
                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                    <flux:table.column>{{ __('SKU') }}</flux:table.column>
                    <flux:table.column>{{ __('Color / Size') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('On Hand') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Reserved') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Available') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->variants as $variant)
                        <flux:table.row :key="$variant->id">
                            <flux:table.cell variant="strong">{{ $variant->product?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $variant->sku ?? $variant->code ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ trim(($variant->color ?? '').' / '.($variant->size ?? ''), ' /') ?: '-' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($variant->stock_on_hand) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($variant->stock_reserved) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <span @class([
                                    'font-semibold',
                                    'text-red-600 dark:text-red-400' => $variant->stock_available <= 5,
                                ])>
                                    {{ number_format($variant->stock_available) }}
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('No variants found.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
