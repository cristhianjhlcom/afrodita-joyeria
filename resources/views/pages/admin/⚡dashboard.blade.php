<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $syncQueued = false;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Dashboard')]);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'brands' => Brand::query()->count(),
            'categories' => Category::query()->count(),
            'products' => Product::query()->count(),
            'variants' => ProductVariant::query()->count(),
            'orders' => Order::query()->count(),
        ];
    }

    #[Computed]
    public function recentSyncRuns()
    {
        return SyncRun::query()
            ->latest('started_at')
            ->limit(8)
            ->get();
    }

    public function queueSync(): void
    {
        Artisan::call('main-store:sync', [
            'resource' => 'all',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Admin Dashboard')" :subheading="__('Monitor sync health and catalog coverage')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Sync queued successfully') }}" />
            @endif

            <div class="grid gap-3 md:grid-cols-5">
                <flux:card>
                    <flux:heading size="sm">{{ __('Brands') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->stats['brands']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Categories') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->stats['categories']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Products') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->stats['products']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Variants') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->stats['variants']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Orders Mirror') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->stats['orders']) }}</flux:text>
                </flux:card>
            </div>

            <div class="flex items-center justify-between gap-3">
                <flux:subheading>{{ __('Recent Sync Runs') }}</flux:subheading>
                <flux:button variant="primary" wire:click="queueSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueSync">{{ __('Queue Full Sync') }}</span>
                    <span wire:loading wire:target="queueSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Resource') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Started') }}</flux:table.column>
                    <flux:table.column>{{ __('Finished') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Processed') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->recentSyncRuns as $syncRun)
                        <flux:table.row :key="$syncRun->id">
                            <flux:table.cell variant="strong">{{ str($syncRun->resource)->headline() }}</flux:table.cell>
                            <flux:table.cell>{{ str($syncRun->status)->headline() }}</flux:table.cell>
                            <flux:table.cell>{{ optional($syncRun->started_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ optional($syncRun->finished_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($syncRun->records_processed) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" :href="route('admin.sync-runs.show', $syncRun)" wire:navigate>
                                    {{ __('View') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('No sync runs yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
