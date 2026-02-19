<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
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

    #[Computed]
    public function syncHealth(): array
    {
        $resources = collect([
            'brands',
            'categories',
            'products',
            'variant-images',
            'variants',
            'inventory',
            'orders',
        ]);

        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestCompletedByResource = SyncRun::query()
            ->where('status', 'completed')
            ->whereIn('resource', $resources->all())
            ->whereNotNull('checkpoint_updated_since')
            ->latest('checkpoint_updated_since')
            ->get()
            ->groupBy('resource')
            ->map(fn ($runs) => $runs->first());

        $missingResources = $resources
            ->reject(fn (string $resource): bool => $latestCompletedByResource->has($resource))
            ->values()
            ->all();

        $staleResources = $latestCompletedByResource
            ->filter(fn (SyncRun $syncRun): bool => $syncRun->checkpoint_updated_since?->lt($staleCutoff) ?? true)
            ->map(fn (SyncRun $syncRun, string $resource): string => sprintf(
                '%s (%s)',
                Str::of($resource)->replace('-', ' ')->headline()->value(),
                optional($syncRun->checkpoint_updated_since)?->diffForHumans() ?? 'never'
            ))
            ->values()
            ->all();

        $latestSuccessfulRun = SyncRun::query()
            ->where('status', 'completed')
            ->whereNotNull('checkpoint_updated_since')
            ->latest('checkpoint_updated_since')
            ->first();

        return [
            'is_stale' => $missingResources !== [] || $staleResources !== [],
            'threshold_minutes' => $thresholdMinutes,
            'last_synced_at' => $latestSuccessfulRun?->checkpoint_updated_since,
            'missing_resources' => $missingResources,
            'stale_resources' => $staleResources,
        ];
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
            @if ($this->syncHealth['is_stale'])
                <flux:callout icon="exclamation-triangle" variant="warning" heading="{{ __('Sync data is stale') }}">
                    <div class="space-y-1 text-sm">
                        <p>
                            {{ __('No successful sync in the last :minutes minutes for one or more resources.', ['minutes' => $this->syncHealth['threshold_minutes']]) }}
                        </p>
                        @if ($this->syncHealth['missing_resources'] !== [])
                            <p>
                                {{ __('Missing successful runs: :resources', ['resources' => collect($this->syncHealth['missing_resources'])->map(fn (string $resource) => str($resource)->replace('-', ' ')->headline()->value())->join(', ')]) }}
                            </p>
                        @endif
                        @if ($this->syncHealth['stale_resources'] !== [])
                            <p>
                                {{ __('Stale resources: :resources', ['resources' => collect($this->syncHealth['stale_resources'])->join(', ')]) }}
                            </p>
                        @endif
                    </div>
                </flux:callout>
            @else
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Sync data is healthy') }}">
                    {{ __('All resources have successful sync checkpoints within the last :minutes minutes.', ['minutes' => $this->syncHealth['threshold_minutes']]) }}
                </flux:callout>
            @endif

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
