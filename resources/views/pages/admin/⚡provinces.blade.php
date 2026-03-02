<?php

use App\Models\Department;
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

    #[Url(as: 'department')]
    public string $department = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Provinces')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->with('country:id,name')->orderBy('name')->get(['id', 'country_id', 'name']);
    }

    #[Computed]
    public function provinces()
    {
        return Province::query()
            ->with(['country:id,name', 'department:id,name'])
            ->withCount('districts')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->department !== '', fn ($query) => $query->where('department_id', (int) $this->department))
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function syncStatus(): array
    {
        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestRun = SyncRun::query()
            ->where('resource', 'provinces')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'provinces')
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

    public function queueProvincesSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'provinces',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Provinces')" :subheading="__('Manage provinces mirrored from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Provinces sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Provinces Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search province')" placeholder="{{ __('Type a province name...') }}" />
                <flux:select wire:model.live="department" :label="__('Department')">
                    <option value="">{{ __('All departments') }}</option>
                    @foreach ($this->departments as $departmentOption)
                        <option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" wire:click="queueProvincesSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueProvincesSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueProvincesSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->provinces">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Country') }}</flux:table.column>
                    <flux:table.column>{{ __('Department') }}</flux:table.column>
                    <flux:table.column>{{ __('Ubigeo') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Shipping Price') }}</flux:table.column>
                    <flux:table.column>{{ __('Districts') }}</flux:table.column>
                    <flux:table.column>{{ __('State') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->provinces as $province)
                        <flux:table.row :key="$province->id">
                            <flux:table.cell variant="strong">{{ $province->name }}</flux:table.cell>
                            <flux:table.cell>{{ $province->country?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $province->department?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $province->ubigeo_code ?? '-' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ money($province->shipping_price, config('services.main_store.currency', 'PEN'))->format() }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($province->districts_count) }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($province->is_active)
                                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($province->remote_updated_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8">{{ __('No provinces found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
