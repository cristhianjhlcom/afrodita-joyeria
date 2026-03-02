<?php

use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Province;
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

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Addresses')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function metrics(): array
    {
        return [
            'countries' => Country::query()->count(),
            'departments' => Department::query()->count(),
            'provinces' => Province::query()->count(),
            'districts' => District::query()->count(),
        ];
    }

    #[Computed]
    public function districts()
    {
        return District::query()
            ->with(['country:id,name', 'department:id,name', 'province:id,name'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('ubigeo_code', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function syncStatus(): array
    {
        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestRun = SyncRun::query()
            ->where('resource', 'addresses')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'addresses')
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

    public function queueAddressesSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'addresses',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
        unset($this->metrics);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Addresses')" :subheading="__('Sync complete address hierarchy from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Addresses sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Addresses Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <flux:card>
                    <flux:heading size="sm">{{ __('Countries') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->metrics['countries']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Departments') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->metrics['departments']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Provinces') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->metrics['provinces']) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Districts') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->metrics['districts']) }}</flux:text>
                </flux:card>
            </div>

            <div class="flex flex-wrap items-end justify-between gap-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search district or ubigeo')" placeholder="{{ __('Type district name or ubigeo...') }}" />
                <flux:button variant="primary" wire:click="queueAddressesSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueAddressesSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueAddressesSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->districts">
                <flux:table.columns>
                    <flux:table.column>{{ __('District') }}</flux:table.column>
                    <flux:table.column>{{ __('Province') }}</flux:table.column>
                    <flux:table.column>{{ __('Department') }}</flux:table.column>
                    <flux:table.column>{{ __('Country') }}</flux:table.column>
                    <flux:table.column>{{ __('Ubigeo') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Shipping Price') }}</flux:table.column>
                    <flux:table.column>{{ __('Express Delivery') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->districts as $district)
                        <flux:table.row :key="$district->id">
                            <flux:table.cell variant="strong">{{ $district->name }}</flux:table.cell>
                            <flux:table.cell>{{ $district->province?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $district->department?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $district->country?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $district->ubigeo_code ?? '-' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ money($district->shipping_price, config('services.main_store.currency', 'PEN'))->format() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($district->has_delivery_express)
                                    <flux:badge color="green">{{ __('Yes') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('No') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($district->remote_updated_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8">{{ __('No address records found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
