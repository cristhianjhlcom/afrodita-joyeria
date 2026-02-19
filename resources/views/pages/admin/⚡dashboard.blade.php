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

    public ?string $queuedResource = null;

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

    /**
     * @return array<int, array{command: string, run_resource: string, label: string}>
     */
    protected function supportedSyncResources(): array
    {
        return [
            ['command' => 'brands', 'run_resource' => 'brands', 'label' => 'Brands'],
            ['command' => 'categories', 'run_resource' => 'categories', 'label' => 'Categories'],
            ['command' => 'products', 'run_resource' => 'products', 'label' => 'Products'],
            ['command' => 'variants', 'run_resource' => 'variants', 'label' => 'Variants'],
            ['command' => 'images', 'run_resource' => 'variant-images', 'label' => 'Variant Images'],
            ['command' => 'inventory', 'run_resource' => 'inventory', 'label' => 'Inventory'],
            ['command' => 'orders', 'run_resource' => 'orders', 'label' => 'Orders'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function supportedSyncCommands(): array
    {
        return collect($this->supportedSyncResources())
            ->pluck('command')
            ->all();
    }

    #[Computed]
    public function resourceSyncStatus(): array
    {
        $resourceMap = collect($this->supportedSyncResources());
        $latestRuns = SyncRun::query()
            ->whereIn('resource', $resourceMap->pluck('run_resource')->all())
            ->latest('started_at')
            ->get()
            ->groupBy('resource')
            ->map(fn ($runs) => $runs->first());

        return $resourceMap
            ->map(function (array $resourceConfig) use ($latestRuns): array {
                /** @var SyncRun|null $run */
                $run = $latestRuns->get($resourceConfig['run_resource']);

                return [
                    'command' => $resourceConfig['command'],
                    'run_resource' => $resourceConfig['run_resource'],
                    'label' => $resourceConfig['label'],
                    'run' => $run,
                    'can_retry' => $run?->status === 'failed',
                ];
            })
            ->all();
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

    #[Computed]
    public function failureAlerts(): array
    {
        $resourceRuns = SyncRun::query()
            ->whereIn('resource', collect($this->supportedSyncResources())->pluck('run_resource')->all())
            ->latest('started_at')
            ->get()
            ->groupBy('resource');

        $threshold = max(1, (int) config('services.main_store.failure_alert_threshold', 3));

        $alerts = [];

        foreach ($resourceRuns as $resource => $runs) {
            $consecutiveFailures = 0;

            foreach ($runs as $run) {
                if ($run->status !== 'failed') {
                    break;
                }

                $consecutiveFailures++;
            }

            if ($consecutiveFailures >= $threshold) {
                $alerts[] = [
                    'resource' => $resource,
                    'resource_label' => str($resource)->replace('-', ' ')->headline()->value(),
                    'failures' => $consecutiveFailures,
                ];
            }
        }

        return [
            'threshold' => $threshold,
            'alerts' => $alerts,
            'has_alerts' => $alerts !== [],
        ];
    }

    public function queueSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'all',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        $this->queuedResource = null;
    }

    public function queueResourceSync(string $resource): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        if (! in_array($resource, $this->supportedSyncCommands(), true)) {
            return;
        }

        Artisan::call('main-store:sync', [
            'resource' => $resource,
            '--queued' => true,
        ]);

        $this->syncQueued = false;
        $this->queuedResource = $resource;
        unset($this->resourceSyncStatus);
        unset($this->recentSyncRuns);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Admin Dashboard')" :subheading="__('Monitor sync health and catalog coverage')">
        <div class="space-y-4">
            @if ($this->failureAlerts['has_alerts'])
                <flux:callout icon="x-circle" variant="danger" heading="{{ __('Repeated sync failures detected') }}">
                    <div class="space-y-1 text-sm">
                        <p>
                            {{ __('One or more resources have reached the failure threshold (:threshold consecutive failures).', ['threshold' => $this->failureAlerts['threshold']]) }}
                        </p>
                        <p>
                            {{ collect($this->failureAlerts['alerts'])->map(fn (array $alert): string => $alert['resource_label'].' ('.$alert['failures'].')')->join(', ') }}
                        </p>
                    </div>
                </flux:callout>
            @endif

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
            @if ($queuedResource !== null)
                <flux:callout
                    icon="check-circle"
                    variant="success"
                    :heading="__('Sync queued for :resource', ['resource' => str($queuedResource)->replace('-', ' ')->headline()->value()])"
                />
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

            <div class="space-y-2">
                <flux:subheading>{{ __('Resource Sync Controls') }}</flux:subheading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Resource') }}</flux:table.column>
                        <flux:table.column>{{ __('Last Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Last Checkpoint') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Errors') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->resourceSyncStatus as $resourceStatus)
                            <flux:table.row :key="$resourceStatus['command']">
                                <flux:table.cell variant="strong">{{ $resourceStatus['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ str($resourceStatus['run']?->status ?? 'never')->headline() }}</flux:table.cell>
                                <flux:table.cell>{{ optional($resourceStatus['run']?->checkpoint_updated_since)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($resourceStatus['run']?->errors_count ?? 0) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="queueResourceSync('{{ $resourceStatus['command'] }}')"
                                            wire:loading.attr="disabled"
                                        >
                                            {{ __('Queue') }}
                                        </flux:button>
                                        @if ($resourceStatus['can_retry'])
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                wire:click="queueResourceSync('{{ $resourceStatus['command'] }}')"
                                                wire:loading.attr="disabled"
                                            >
                                                {{ __('Retry Failed') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </x-pages::admin.layout>
</section>
