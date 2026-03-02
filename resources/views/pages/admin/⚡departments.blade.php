<?php

use App\Models\Country;
use App\Models\Department;
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

    #[Url(as: 'country')]
    public string $country = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Departments')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCountry(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function countries()
    {
        return Country::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function departments()
    {
        return Department::query()
            ->with(['country'])
            ->withCount(['provinces', 'districts'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->country !== '', fn ($query) => $query->where('country_id', (int) $this->country))
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function syncStatus(): array
    {
        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestRun = SyncRun::query()
            ->where('resource', 'departments')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'departments')
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

    public function queueDepartmentsSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'departments',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Departments')" :subheading="__('Manage departments mirrored from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Departments sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Departments Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search department')" placeholder="{{ __('Type a department name...') }}" />
                <flux:select wire:model.live="country" :label="__('Country')">
                    <option value="">{{ __('All countries') }}</option>
                    @foreach ($this->countries as $countryOption)
                        <option value="{{ $countryOption->id }}">{{ $countryOption->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" wire:click="queueDepartmentsSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueDepartmentsSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueDepartmentsSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->departments">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Country') }}</flux:table.column>
                    <flux:table.column>{{ __('Ubigeo') }}</flux:table.column>
                    <flux:table.column>{{ __('Provinces') }}</flux:table.column>
                    <flux:table.column>{{ __('Districts') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->departments as $department)
                        <flux:table.row :key="$department->id">
                            <flux:table.cell variant="strong">{{ $department->name }}</flux:table.cell>
                            <flux:table.cell>{{ $department->country?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $department->ubigeo_code ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($department->provinces_count) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($department->districts_count) }}</flux:table.cell>
                            <flux:table.cell>{{ optional($department->remote_updated_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $department->external_id }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('No departments found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
