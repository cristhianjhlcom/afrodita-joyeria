<?php

use App\Models\Country;
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
        $view->layout('layouts.admin', ['title' => __('Admin Countries')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function countries()
    {
        return Country::query()
            ->withCount(['departments', 'provinces', 'districts'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function syncStatus(): array
    {
        $thresholdMinutes = (int) config('services.main_store.stale_threshold_minutes', 60);
        $staleCutoff = now()->subMinutes($thresholdMinutes);

        $latestRun = SyncRun::query()
            ->where('resource', 'countries')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'countries')
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

    public function queueCountriesSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'countries',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Countries')" :subheading="__('Manage countries mirrored from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Countries sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Countries Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="flex flex-wrap items-end justify-between gap-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search country')" placeholder="{{ __('Type a country name...') }}" />
                <flux:button variant="primary" wire:click="queueCountriesSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueCountriesSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueCountriesSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->countries">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('ISO2') }}</flux:table.column>
                    <flux:table.column>{{ __('ISO3') }}</flux:table.column>
                    <flux:table.column>{{ __('Departments') }}</flux:table.column>
                    <flux:table.column>{{ __('Provinces') }}</flux:table.column>
                    <flux:table.column>{{ __('Districts') }}</flux:table.column>
                    <flux:table.column>{{ __('State') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->countries as $country)
                        <flux:table.row :key="$country->id">
                            <flux:table.cell variant="strong">{{ $country->name }}</flux:table.cell>
                            <flux:table.cell>{{ $country->iso_code_2 ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $country->iso_code_3 ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($country->departments_count) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($country->provinces_count) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($country->districts_count) }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($country->is_active)
                                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($country->remote_updated_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $country->external_id }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">{{ __('No countries found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
