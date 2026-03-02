<?php

use App\Models\Brand;
use App\Models\BrandWhitelist;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?string $integrationSavedFor = null;

    public bool $showCreateIntegrationModal = false;

    public bool $showEditIntegrationModal = false;

    public ?int $editingBrandId = null;

    public string $editingBrandName = '';

    public string $editingBrandToken = '';

    public string $newBrandName = '';

    public string $newBrandSlug = '';

    public ?int $newBrandExternalId = null;

    public string $newBrandToken = '';

    public bool $syncQueued = false;

    #[Url(as: 'q')]
    public string $search = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Brands')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function brands()
    {
        return Brand::query()
            ->with('whitelist')
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
            ->where('resource', 'brands')
            ->latest('started_at')
            ->first();

        $latestSuccessfulRun = SyncRun::query()
            ->where('resource', 'brands')
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

    public function queueBrandsSync(): void
    {
        abort_unless(auth()->user()?->can('trigger', SyncRun::class), 403);

        Artisan::call('main-store:sync', [
            'resource' => 'brands',
            '--queued' => true,
        ]);

        $this->syncQueued = true;
        unset($this->syncStatus);
    }

    public function openCreateIntegrationModal(): void
    {
        abort_unless(auth()->user()?->can('toggleWhitelist', Brand::class), 403);

        $this->resetValidation();
        $this->reset(['newBrandName', 'newBrandSlug', 'newBrandExternalId', 'newBrandToken']);
        $this->showCreateIntegrationModal = true;
    }

    public function openEditIntegrationModal(int $brandId): void
    {
        abort_unless(auth()->user()?->can('toggleWhitelist', Brand::class), 403);

        $brand = Brand::query()->with('whitelist')->findOrFail($brandId);

        $this->resetValidation();
        $this->editingBrandId = $brand->id;
        $this->editingBrandName = $brand->name;
        $this->editingBrandToken = (string) ($brand->whitelist?->main_store_token ?? '');
        $this->showEditIntegrationModal = true;
    }

    public function toggleWhitelist(int $brandId): void
    {
        abort_unless(auth()->user()?->can('toggleWhitelist', Brand::class), 403);

        $brand = Brand::query()->findOrFail($brandId);

        $whitelist = BrandWhitelist::query()->firstOrCreate([
            'brand_id' => $brand->id,
        ], [
            'enabled' => false,
        ]);

        if (! $whitelist->enabled && trim((string) $whitelist->main_store_token) === '') {
            $this->addError('integration', __('Set a main store token before enabling this brand.'));

            return;
        }

        $whitelist->update([
            'enabled' => ! $whitelist->enabled,
        ]);

        $this->integrationSavedFor = null;
        unset($this->brands);
    }

    public function createBrandIntegration(): void
    {
        abort_unless(auth()->user()?->can('toggleWhitelist', Brand::class), 403);

        $validated = $this->validate([
            'newBrandName' => ['required', 'string', 'max:255'],
            'newBrandSlug' => ['nullable', 'string', 'max:255', 'unique:brands,slug'],
            'newBrandExternalId' => ['required', 'integer', 'min:1', 'unique:brands,external_id'],
            'newBrandToken' => ['required', 'string', 'max:255'],
        ], [
            'newBrandName.required' => __('Brand name is required.'),
            'newBrandExternalId.required' => __('Main store ID is required.'),
            'newBrandExternalId.unique' => __('That main store ID already exists.'),
            'newBrandToken.required' => __('Main store token is required.'),
        ]);

        $slug = trim((string) $validated['newBrandSlug']);
        if ($slug === '') {
            $slug = Str::slug((string) $validated['newBrandName']);
        }

        if ($slug === '') {
            $slug = 'brand-'.$validated['newBrandExternalId'];
        }

        if (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.$validated['newBrandExternalId'];
        }

        $brand = Brand::query()->create([
            'name' => (string) $validated['newBrandName'],
            'slug' => $slug,
            'external_id' => (int) $validated['newBrandExternalId'],
            'is_active' => true,
        ]);

        BrandWhitelist::query()->updateOrCreate([
            'brand_id' => $brand->id,
        ], [
            'enabled' => true,
            'main_store_token' => (string) $validated['newBrandToken'],
        ]);

        $this->integrationSavedFor = $brand->name;
        $this->showCreateIntegrationModal = false;
        $this->reset(['newBrandName', 'newBrandSlug', 'newBrandExternalId', 'newBrandToken']);
        unset($this->brands);
    }

    public function saveEditedIntegration(): void
    {
        abort_unless(auth()->user()?->can('toggleWhitelist', Brand::class), 403);

        $validated = $this->validate([
            'editingBrandId' => ['required', 'integer', 'exists:brands,id'],
            'editingBrandToken' => ['required', 'string', 'max:255'],
        ], [
            'editingBrandToken.required' => __('Main store token is required.'),
        ]);

        $brand = Brand::query()->findOrFail((int) $validated['editingBrandId']);

        BrandWhitelist::query()->updateOrCreate([
            'brand_id' => $brand->id,
        ], [
            'main_store_token' => trim((string) $validated['editingBrandToken']),
        ]);

        $this->integrationSavedFor = $brand->name;
        $this->showEditIntegrationModal = false;
        unset($this->brands);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Brands')" :subheading="__('Whitelist brands, configure integrations, and monitor sync readiness')">
        <div class="space-y-4">
            @if ($integrationSavedFor !== null)
                <flux:callout
                    icon="check-circle"
                    variant="success"
                    :heading="__('Integration updated for :brand', ['brand' => $integrationSavedFor])"
                />
            @endif

            @if ($syncQueued)
                <flux:callout icon="check-circle" variant="success" heading="{{ __('Brands sync queued successfully') }}" />
            @endif

            @error('integration')
                <flux:callout icon="x-circle" variant="danger" :heading="$message" />
            @enderror

            <flux:card class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('Brands Sync Status') }}</flux:heading>
                    <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                    <span>{{ __('Last synced: :value', ['value' => optional($this->syncStatus['last_synced_at'])?->diffForHumans() ?? __('Never')]) }}</span>
                    <span>{{ __('Errors: :count', ['count' => number_format($this->syncStatus['error_count'])]) }}</span>
                </div>
            </flux:card>

            <div class="flex flex-wrap items-end justify-between gap-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search brand')" placeholder="{{ __('Type a brand name...') }}" />
                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="queueBrandsSync" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="queueBrandsSync">{{ __('Queue Sync') }}</span>
                        <span wire:loading wire:target="queueBrandsSync">{{ __('Queuing...') }}</span>
                    </flux:button>
                    <flux:button variant="primary" wire:click="openCreateIntegrationModal" wire:loading.attr="disabled">
                        {{ __('New Integration') }}
                    </flux:button>
                </div>
            </div>

            <flux:text class="text-sm text-zinc-600">
                {{ __('Main Store ID must be the real brand ID from your main store API. Do not use random numbers.') }}
            </flux:text>

            <flux:table :paginate="$this->brands">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Slug') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Brand State') }}</flux:table.column>
                    <flux:table.column>{{ __('Integration') }}</flux:table.column>
                    <flux:table.column>{{ __('Whitelisted') }}</flux:table.column>
                    <flux:table.column>{{ __('Sync Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->brands as $brand)
                        <flux:table.row :key="$brand->id">
                            <flux:table.cell variant="strong">{{ $brand->name }}</flux:table.cell>
                            <flux:table.cell>{{ $brand->slug ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $brand->external_id ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($brand->is_active)
                                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="amber">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if (filled($brand->whitelist?->main_store_token))
                                    <flux:badge color="green">{{ __('Configured') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('Missing Token') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($brand->whitelist?->enabled)
                                    <flux:badge color="green">{{ __('Yes') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('No') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$this->syncStatus['badge_color']">{{ $this->syncStatus['state_label'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($this->syncStatus['last_synced_at'])?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="openEditIntegrationModal({{ $brand->id }})" wire:loading.attr="disabled">
                                        {{ __('Edit Integration') }}
                                    </flux:button>
                                    <flux:button size="sm" wire:click="toggleWhitelist({{ $brand->id }})" wire:loading.attr="disabled">
                                        {{ $brand->whitelist?->enabled ? __('Disable') : __('Enable') }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">{{ __('No brands found for the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>

    <flux:modal wire:model.self="showCreateIntegrationModal" variant="flyout" position="right" class="w-full max-w-xl">
        <form wire:submit="createBrandIntegration" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Create Brand Integration') }}</flux:heading>
                <flux:subheading>{{ __('Register a new brand and its token from your main store.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Brand Name') }}</flux:label>
                <flux:input wire:model="newBrandName" placeholder="{{ __('Afrodita') }}" />
                <flux:error name="newBrandName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Main Store ID') }}</flux:label>
                <flux:input wire:model="newBrandExternalId" type="number" min="1" placeholder="{{ __('11001') }}" />
                <flux:text>{{ __('Use the exact brand ID from the main store DB/API.') }}</flux:text>
                <flux:error name="newBrandExternalId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Slug (optional)') }}</flux:label>
                <flux:input wire:model="newBrandSlug" placeholder="{{ __('afrodita') }}" />
                <flux:error name="newBrandSlug" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Main Store Token') }}</flux:label>
                <flux:input wire:model="newBrandToken" type="password" placeholder="{{ __('Paste token') }}" />
                <flux:error name="newBrandToken" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Integration') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditIntegrationModal" variant="flyout" position="right" class="w-full max-w-xl">
        <form wire:submit="saveEditedIntegration" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Integration') }}</flux:heading>
                <flux:subheading>{{ __('Update token for :brand', ['brand' => $editingBrandName !== '' ? $editingBrandName : __('Brand')]) }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Main Store Token') }}</flux:label>
                <flux:input wire:model="editingBrandToken" type="password" placeholder="{{ __('Paste token') }}" />
                <flux:error name="editingBrandToken" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Integration') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
