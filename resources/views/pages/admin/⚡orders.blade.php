<?php

use App\Models\Order;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', ['title' => __('Admin Orders')]);
    }

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'source')]
    public string $source = '';

    #[Url(as: 'push')]
    public string $pushStatus = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function updatedPushStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        $searchTerm = trim($this->search);

        return Order::query()
            ->withCount('items')
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                if (ctype_digit($searchTerm)) {
                    $query->where(function ($innerQuery) use ($searchTerm): void {
                        $innerQuery
                            ->where('external_id', (int) $searchTerm)
                            ->orWhere('id', (int) $searchTerm);
                    });

                    return;
                }

                $query->where(function ($innerQuery) use ($searchTerm): void {
                    $innerQuery
                        ->where('main_store_external_order_id', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_email', 'like', "%{$searchTerm}%");
                });
            })
            ->when($this->source !== '', function ($query): void {
                if ($this->source === 'main_store') {
                    $query->whereNull('source');

                    return;
                }

                $query->where('source', $this->source);
            })
            ->when($this->pushStatus !== '', fn ($query) => $query->where('push_status', $this->pushStatus))
            ->latest('placed_at')
            ->paginate(12);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('Orders')" :subheading="__('Local checkout orders and main store mirrors')">
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search order')" placeholder="{{ __('ID, token, or email...') }}" />

                <flux:field>
                    <flux:label>{{ __('Source') }}</flux:label>
                    <flux:select wire:model.live="source">
                        <option value="">{{ __('All sources') }}</option>
                        <option value="local_checkout">{{ __('Local checkout') }}</option>
                        <option value="main_store">{{ __('Main store sync') }}</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Push status') }}</flux:label>
                    <flux:select wire:model.live="pushStatus">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="pushed">{{ __('Pushed') }}</option>
                        <option value="failed">{{ __('Failed') }}</option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:table :paginate="$this->orders">
                <flux:table.columns>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                    <flux:table.column>{{ __('Push') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Payment') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Items') }}</flux:table.column>
                    <flux:table.column>{{ __('Currency') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Grand Total') }}</flux:table.column>
                    <flux:table.column>{{ __('Placed At') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->orders as $order)
                        <flux:table.row :key="$order->id">
                            <flux:table.cell variant="strong">
                                {{ $order->external_id ?? $order->main_store_external_order_id ?? $order->id }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge>
                                    {{ $order->source === 'local_checkout' ? __('Local checkout') : __('Main store') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($order->source === 'local_checkout')
                                    <flux:badge color="{{ $order->push_status === 'failed' ? 'rose' : ($order->push_status === 'pushed' ? 'emerald' : 'amber') }}">
                                        {{ str((string) $order->push_status)->headline() }}
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-slate-500">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ str($order->status)->headline() }}</flux:table.cell>
                            <flux:table.cell>{{ str((string) $order->payment_status)->headline() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($order->items_count) }}</flux:table.cell>
                            <flux:table.cell>{{ $order->currency }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($order->grand_total / 100, 2) }}</flux:table.cell>
                            <flux:table.cell>{{ optional($order->placed_at)?->format('Y-m-d H:i') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">{{ __('No orders yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-pages::admin.layout>
</section>
