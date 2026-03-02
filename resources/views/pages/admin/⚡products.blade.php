<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public bool $syncQueued = false;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'b')]
    public string $brand = '';

    #[Url(as: 'c')]
    public string $category = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Products')]);
    }

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
            ->with([
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'url', 'is_primary', 'sort_order'])
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order'),
            ])
            ->withCount('variants')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->brand !== '', fn ($query) => $query->where('brand_id', (int) $this->brand))
            ->when($this->category !== '', fn ($query) => $query->where('subcategory_id', (int) $this->category))
            ->latest('updated_at')
            ->paginate(12);
    }

    #[Computed]
    public function syncStatus(): array
    {
        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestRun = SyncRun::query()
            ->where('resource', 'products')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'products')
            ->where('status', 'completed')
            ->whereNotNull('checkpoint_updated_since')
            ->latest('checkpoint_updated_since')
            ->first();

        $stateLabel = __('Never Synced');
        $badgeColor = null;

        if ($latestRun?->status === 'running') {
            $stateLabel = __('Running');
            $badgeColor = 'blue';
        } elseif ($latestRun?->status === 'failed') {
            $stateLabel = __('Failed');
            $badgeColor = 'red';
        } elseif ($latestSuccessfulRun?->checkpoint_updated_since?->lt($staleCutoff) ?? true) {
            $stateLabel = __('Stale');
            $badgeColor = 'amber';
        } elseif ($latestSuccessfulRun !== null) {
            $stateLabel = __('Healthy');
            $badgeColor = 'green';
        }

        return [
            'state_label' => $stateLabel,
            'badge_color' => $badgeColor,
            'last_synced_at' => $latestSuccessfulRun?->checkpoint_updated_since,
            'error_count' => $latestRun?->errors_count ?? 0,
        ];
    }

    public function queueProductsSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'products',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Products')" :subheading="__('Read-only catalog projection from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Products sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Products Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

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

            <div class="flex items-center justify-end">
                <flux:button variant="primary" wire:click="queueProductsSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueProductsSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueProductsSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->products">
                <flux:table.columns>
                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                    <flux:table.column>{{ __('Brand') }}</flux:table.column>
                    <flux:table.column>{{ __('Subcategory') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Sync Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                    <flux:table.column>{{ __('Variants') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->products as $product)
                        <flux:table.row :key="$product->id">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-3">
                                    <flux:avatar
                                        size="sm"
                                        :name="$product->name"
                                        :src="$product->images->first()?->url"
                                        :alt="$product->name"
                                    />
                                    <span>{{ $product->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $product->brand?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $product->subcategory?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @if (in_array($product->status, ['published', 'in_stock'], true))
                                    <flux:badge color="green">{{ str($product->status)->replace('_', ' ')->headline() }}</flux:badge>
                                @elseif (in_array($product->status, ['out_of_stock', 'sold_out', 'discontinued'], true))
                                    <flux:badge color="amber">{{ str($product->status)->replace('_', ' ')->headline() }}</flux:badge>
                                @else
                                    <flux:badge>{{ str($product->status)->replace('_', ' ')->headline() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($this->syncStatus['last_synced_at'])?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
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
                            <flux:table.cell colspan="9">{{ __('No products found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
