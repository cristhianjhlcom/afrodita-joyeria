<?php

use App\Models\ProductVariant;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Inventory')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'low')]
    public bool $onlyLowStock = false;

    public bool $syncQueued = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyLowStock(): void
    {
        $this->resetPage();
    }

    public function queueInventorySync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'inventory',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
    }

    #[Computed]
    public function variants()
    {
        $searchTerm = trim($this->search);

        return ProductVariant::query()
            ->with('product')
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->where('sku', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$searchTerm}%"));
                });
            })
            ->when($this->onlyLowStock, fn ($query) => $query->where('stock_available', '<=', 5))
            ->latest('updated_at')
            ->paginate(15);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Inventory')" :subheading="__('Monitor variant stock levels synced from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Inventory sync queued successfully') }}" />
            @endif

            <div class="flex items-center justify-end">
                <flux:button variant="primary" wire:click="queueInventorySync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueInventorySync">{{ __('Queue Inventory Sync') }}</span>
                    <span wire:loading wire:target="queueInventorySync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search SKU / Product')" placeholder="{{ __('Type SKU, code, or product name...') }}" />

                <flux:field variant="inline" class="rounded-lg border border-zinc-200 px-3 py-2 md:justify-self-end dark:border-zinc-700">
                    <div class="space-y-1">
                        <flux:label>{{ __('Only Low Stock') }}</flux:label>
                        <flux:description>{{ __('Show variants with 5 or fewer units available') }}</flux:description>
                    </div>

                    <flux:switch wire:model.live="onlyLowStock" align="end" />
                </flux:field>
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
