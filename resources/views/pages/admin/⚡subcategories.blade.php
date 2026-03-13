<?php

use App\Models\Category;
use App\Models\Subcategory;
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

    public bool $syncQueued = false;

    #[Url(as: 'q')]
    public string $search = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Subcategories')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function subcategories()
    {
        return Subcategory::query()
            ->with('category:id,name')
            ->withCount('products')
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
            ->where('resource', 'categories')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'categories')
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

    public function queueCategoriesSync(): void
    {
        abort_unless(auth()->user()?->can('viewAny', Category::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'categories',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Subcategories')" :subheading="__('Manage subcategories mirrored from the main store')">
        <div class="space-y-4">
            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Subcategories sync queued successfully') }}" />
            @endif

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Subcategories Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="flex flex-wrap items-end justify-between gap-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search subcategory')" placeholder="{{ __('Type a subcategory name...') }}" />
                <flux:button variant="primary" wire:click="queueCategoriesSync" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="queueCategoriesSync">{{ __('Queue Sync') }}</span>
                    <span wire:loading wire:target="queueCategoriesSync">{{ __('Queuing...') }}</span>
                </flux:button>
            </div>

            <flux:table :paginate="$this->subcategories">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Products') }}</flux:table.column>
                    <flux:table.column>{{ __('Sync Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->subcategories as $subcategory)
                        <flux:table.row :key="$subcategory->id">
                            <flux:table.cell variant="strong">{{ $subcategory->name }}</flux:table.cell>
                            <flux:table.cell>{{ $subcategory->category?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($subcategory->products_count) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($this->syncStatus['last_synced_at'])?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $subcategory->external_id ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('No subcategories found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
