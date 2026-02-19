<?php

use App\Models\Order;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Orders')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->withCount('items')
            ->when($this->search !== '', fn ($query) => $query->where('external_id', 'like', "%{$this->search}%"))
            ->latest('placed_at')
            ->paginate(12);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Orders Mirror')" :subheading="__('Read-only mirrored orders from the main store')">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search external order ID')" placeholder="{{ __('Type order ID...') }}" />

            <flux:table :paginate="$this->orders">
                <flux:table.columns>
                    <flux:table.column>{{ __('External ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Items') }}</flux:table.column>
                    <flux:table.column>{{ __('Currency') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Grand Total') }}</flux:table.column>
                    <flux:table.column>{{ __('Placed At') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->orders as $order)
                        <flux:table.row :key="$order->id">
                            <flux:table.cell variant="strong">{{ $order->external_id ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ str($order->status)->headline() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($order->items_count) }}</flux:table.cell>
                            <flux:table.cell>{{ $order->currency }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($order->grand_total / 100, 2) }}</flux:table.cell>
                            <flux:table.cell>{{ optional($order->placed_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('No orders mirrored yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
