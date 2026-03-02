<?php

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

    #[Url(as: 'province')]
    public string $province = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Districts')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProvince(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function provinces()
    {
        return Province::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function districts()
    {
        return District::query()
            ->with(['country:id,name', 'department:id,name', 'province:id,name'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->province !== '', fn ($query) => $query->where('province_id', (int) $this->province))
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function syncStatus(): array
    {
        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestRun = SyncRun::query()
            ->where('resource', 'districts')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'districts')
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

    public function queueDistrictsSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'districts',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Districts')" :subheading="__('Manage districts mirrored from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Districts sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Districts Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search district')" placeholder="{{ __('Type a district name...') }}" />
                <flux:select wire:model.live="province" :label="__('Province')">
                    <option value="">{{ __('All provinces') }}</option>
                    @foreach ($this->provinces as $provinceOption)
                        <option value="{{ $provinceOption->id }}">{{ $provinceOption->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" wire:click="queueDistrictsSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueDistrictsSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueDistrictsSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->districts">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Country') }}</flux:table.column>
                    <flux:table.column>{{ __('Department') }}</flux:table.column>
                    <flux:table.column>{{ __('Province') }}</flux:table.column>
                    <flux:table.column>{{ __('Ubigeo') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Shipping Price') }}</flux:table.column>
                    <flux:table.column>{{ __('Express Delivery') }}</flux:table.column>
                    <flux:table.column>{{ __('State') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->districts as $district)
                        <flux:table.row :key="$district->id">
                            <flux:table.cell variant="strong">{{ $district->name }}</flux:table.cell>
                            <flux:table.cell>{{ $district->country?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $district->department?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $district->province?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $district->ubigeo_code ?? '-' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ money($district->shipping_price, config('services.main_store.currency', 'PEN'))->format() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($district->has_delivery_express)
                                    <flux:badge color="green">{{ __('Yes') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('No') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($district->is_active)
                                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($district->remote_updated_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">{{ __('No districts found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
