<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function rendering(View $view): void
    {
        $view->layout('layouts.storefront', [
            'title' => __('My Orders').' | '.config('app.name'),
        ]);
    }

    #[Computed]
    public function orders(): Collection
    {
        $user = Auth::user();

        return Order::query()
            ->where('source', 'local_checkout')
            ->where(function ($query) use ($user): void {
                $query->where('user_id', (int) $user->id)
                    ->orWhere('customer_email', (string) $user->email);
            })
            ->with('items')
            ->latest('placed_at')
            ->latest('id')
            ->get();
    }

    /**
     * @return array<int, array{order: Order, items: Collection<int, array{item: OrderItem, image_url: ?string}>}>
     */
    #[Computed]
    public function detailedOrders(): array
    {
        $orders = $this->orders;

        $externalIds = $orders
            ->flatMap(fn (Order $order): Collection => $order->items->pluck('variant_external_id'))
            ->filter(fn (?int $id): bool => $id !== null)
            ->unique()
            ->values();

        $variants = ProductVariant::query()
            ->whereIn('external_id', $externalIds)
            ->with(['product:id,name,featured_image'])
            ->get(['id', 'external_id', 'product_id', 'primary_image_url'])
            ->keyBy('external_id');

        return $orders->map(function (Order $order) use ($variants): array {
            $items = $order->items->map(function (OrderItem $item) use ($variants): array {
                $variant = $item->variant_external_id ? $variants->get((int) $item->variant_external_id) : null;
                $imageUrl = $variant?->primary_image_url ?: $variant?->product?->featured_image;

                return [
                    'item' => $item,
                    'image_url' => $imageUrl,
                ];
            });

            return [
                'order' => $order,
                'items' => $items,
            ];
        })->all();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('My Orders') }}</flux:heading>

    <x-pages::settings.layout :heading="__('My Orders')" :subheading="__('Track your recent purchases and payment status')">
        <div class="my-6 w-full space-y-4">
            @forelse ($this->detailedOrders as $entry)
                @php($order = $entry['order'])
                <article class="space-y-4 rounded-sm border border-slate-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-zinc-100">{{ __('Order #:id', ['id' => $order->id]) }}</h2>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ optional($order->placed_at)?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge>{{ str($order->status)->headline() }}</flux:badge>
                            <flux:badge color="emerald">{{ str((string) $order->payment_status)->headline() }}</flux:badge>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-zinc-700">
                        @foreach ($entry['items'] as $row)
                            @php($item = $row['item'])
                            <div class="flex items-center gap-3">
                                <div class="size-16 shrink-0 overflow-hidden rounded-sm border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800">
                                    @if ($row['image_url'])
                                        <img src="{{ $row['image_url'] }}" alt="{{ $item->name_snapshot }}" class="h-full w-full object-cover" loading="lazy">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ __('No image') }}</div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ $item->name_snapshot }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Quantity') }}: {{ $item->qty }}</p>
                                </div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ money($item->line_total, $order->currency)->format() }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end border-t border-slate-200 pt-4 dark:border-zinc-700">
                        <p class="text-base font-semibold text-slate-900 dark:text-zinc-100">{{ __('Total: :total', ['total' => money($order->grand_total, $order->currency)->format()]) }}</p>
                    </div>
                </article>
            @empty
                <div class="rounded-sm border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ __('You do not have any orders yet.') }}
                </div>
            @endforelse
        </div>
    </x-pages::settings.layout>
</section>
