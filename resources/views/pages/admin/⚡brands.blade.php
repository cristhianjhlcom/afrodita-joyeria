<?php

use App\Models\Brand;
use App\Models\BrandWhitelist;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Brands')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

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

    public function toggleWhitelist(int $brandId): void
    {
        abort_unless(auth()->user()?->can('toggleWhitelist', Brand::class), 403);

        $brand = Brand::query()->findOrFail($brandId);

        $whitelist = BrandWhitelist::query()->firstOrCreate([
            'brand_id' => $brand->id,
        ], [
            'enabled' => false,
        ]);

        $whitelist->update([
            'enabled' => ! $whitelist->enabled,
        ]);

        unset($this->brands);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Brands')" :subheading="__('Whitelist brands to include catalog products from the main store')">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search brand')" placeholder="{{ __('Type a brand name...') }}" />

            <flux:table :paginate="$this->brands">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Slug') }}</flux:table.column>
                    <flux:table.column>{{ __('Main Store ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Whitelisted') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->brands as $brand)
                        <flux:table.row :key="$brand->id">
                            <flux:table.cell variant="strong">{{ $brand->name }}</flux:table.cell>
                            <flux:table.cell>{{ $brand->slug ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $brand->external_id ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($brand->whitelist?->enabled)
                                    <flux:badge color="green">{{ __('Yes') }}</flux:badge>
                                @else
                                    <flux:badge>{{ __('No') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" wire:click="toggleWhitelist({{ $brand->id }})" wire:loading.attr="disabled">
                                    {{ $brand->whitelist?->enabled ? __('Disable') : __('Enable') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __('No brands found.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
