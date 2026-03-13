<?php

use App\Services\Storefront\CartService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $feedbackMessage = null;

    public bool $feedbackSuccess = true;

    public function rendering(View $view): void
    {
        $view->layout('layouts.storefront', [
            'title' => __('Cart').' | '.config('app.name'),
        ]);
    }

    #[Computed]
    public function cartItems(): Collection
    {
        return app(CartService::class)->detailedItems();
    }

    #[Computed]
    public function cartSummary(): array
    {
        return app(CartService::class)->summary();
    }

    public function increase(int $variantId): void
    {
        $this->applyResult(app(CartService::class)->increment($variantId));
    }

    public function decrease(int $variantId): void
    {
        $this->applyResult(app(CartService::class)->decrement($variantId));
    }

    public function removeItem(int $variantId): void
    {
        $this->applyResult(app(CartService::class)->remove($variantId));
    }

    public function clearCart(): void
    {
        app(CartService::class)->clear();

        $this->feedbackSuccess = true;
        $this->feedbackMessage = __('Cart cleared.');

        unset($this->cartItems, $this->cartSummary);
        $this->dispatch('cart-updated');
    }

    /**
     * @param  array{ok: bool, message: string, code: string}  $result
     */
    protected function applyResult(array $result): void
    {
        $this->feedbackSuccess = (bool) $result['ok'];
        $this->feedbackMessage = (string) $result['message'];

        unset($this->cartItems, $this->cartSummary);

        if ($result['ok']) {
            $this->dispatch('cart-updated');
        }
    }
}; ?>

<section class="space-y-6">
    <header class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Cart') }}</h1>
        <p class="text-sm text-slate-600 dark:text-zinc-300">{{ __('Review your products before processing the purchase.') }}</p>
    </header>

    @if ($feedbackMessage)
        <div class="rounded-sm border px-4 py-3 text-sm {{ $feedbackSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
            {{ $feedbackMessage }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-3">
            @forelse ($this->cartItems as $item)
                @php($variant = $item['variant'])
                @php($imageUrl = $variant->primary_image_url ?: $variant->product?->primaryImageUrl())
                <article wire:key="cart-page-item-{{ $item['variant_id'] }}" class="space-y-3 rounded-sm border border-slate-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex gap-3">
                        <div class="size-20 shrink-0 overflow-hidden rounded-sm border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $variant->product?->name ?? __('Product') }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ __('No image') }}</div>
                            @endif
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-zinc-100">{{ $variant->product?->name ?? __('Product') }}</h2>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">
                                {{ __('Size') }}: {{ $variant->size ?: __('One Size') }}
                                <span>·</span>
                                {{ __('Color') }}: {{ $variant->color ?: __('Default') }}
                            </p>
                            <p class="text-xs {{ $item['stock_available'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                                {{ __('Stock available: :stock', ['stock' => $item['stock_available']]) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="inline-flex items-center rounded-sm border border-slate-300 dark:border-zinc-700">
                            <button
                                type="button"
                                wire:click="decrease({{ $item['variant_id'] }})"
                                class="inline-flex min-h-10 min-w-10 items-center justify-center text-slate-700 transition hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                <flux:icon.minus class="size-4" />
                            </button>
                            <span class="inline-flex min-h-10 min-w-10 items-center justify-center border-x border-slate-300 px-3 text-sm font-semibold dark:border-zinc-700">
                                {{ $item['quantity'] }}
                            </span>
                            <button
                                type="button"
                                wire:click="increase({{ $item['variant_id'] }})"
                                class="inline-flex min-h-10 min-w-10 items-center justify-center text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                @disabled($item['quantity'] >= $item['stock_available'])
                            >
                                <flux:icon.plus class="size-4" />
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <p class="text-base font-semibold text-slate-900 dark:text-zinc-100">
                                {{ money($item['line_total'], $this->cartSummary['currency'])->format() }}
                            </p>
                            <button
                                type="button"
                                wire:click="removeItem({{ $item['variant_id'] }})"
                                class="inline-flex size-10 items-center justify-center rounded-sm border border-rose-300 text-rose-700 transition hover:bg-rose-50 dark:border-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/20"
                                aria-label="{{ __('Remove item') }}"
                            >
                                <flux:icon.trash class="size-4" />
                            </button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-sm border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ __('Your cart is empty.') }}
                </div>
            @endforelse
        </div>

        <aside class="space-y-4 rounded-sm border border-slate-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">{{ __('Summary') }}</h2>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between text-slate-600 dark:text-zinc-300">
                    <span>{{ __('Items') }}</span>
                    <span>{{ $this->cartSummary['items_count'] }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-600 dark:text-zinc-300">
                    <span>{{ __('Subtotal') }}</span>
                    <span class="font-semibold text-slate-900 dark:text-zinc-100">
                        {{ money($this->cartSummary['subtotal_minor'], $this->cartSummary['currency'])->format() }}
                    </span>
                </div>
            </div>

            <div class="space-y-2 pt-2">
                <a
                    href="{{ route('storefront.checkout.show') }}"
                    wire:navigate
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-amber-300 bg-amber-300 px-4 text-sm font-semibold text-slate-900 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled($this->cartSummary['items_count'] === 0)
                >
                    {{ __('Go to checkout') }}
                </a>
                <button
                    type="button"
                    wire:click="clearCart"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @disabled($this->cartSummary['items_count'] === 0)
                >
                    {{ __('Clear cart') }}
                </button>
                <a
                    href="{{ route('home') }}"
                    wire:navigate
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                >
                    {{ __('Continue shopping') }}
                </a>
            </div>
        </aside>
    </div>
</section>
