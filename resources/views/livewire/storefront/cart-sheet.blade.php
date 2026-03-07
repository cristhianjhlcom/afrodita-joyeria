<div class="contents">
    <button
        type="button"
        wire:click="open"
        class="inline-flex min-h-11 items-center gap-1.5 rounded-sm border border-slate-600 bg-slate-800 px-3 text-xs font-semibold uppercase tracking-wide text-slate-100 transition hover:border-amber-400 hover:text-amber-300 sm:text-sm"
        aria-label="{{ __('Shopping cart') }}"
    >
        <flux:icon.shopping-cart class="size-4" />
        <span>{{ __('Cart') }}</span>
        <span class="inline-flex size-5 items-center justify-center rounded-sm bg-slate-700 text-[11px] font-bold text-slate-100">
            {{ $cartSummary['items_count'] }}
        </span>
    </button>

    <flux:modal wire:model.self="show" variant="flyout" position="right" class="w-full max-w-xl">
        <div class="space-y-5">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Your cart') }}</flux:heading>
                <flux:subheading>
                    {{ trans_choice(':count item|:count items', $cartSummary['items_count'], ['count' => $cartSummary['items_count']]) }}
                </flux:subheading>
            </div>

            @if ($feedbackMessage)
                <div class="rounded-sm border px-3 py-2 text-sm {{ $feedbackSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                    {{ $feedbackMessage }}
                </div>
            @endif

            <div class="max-h-[56vh] space-y-3 overflow-auto pr-1">
                @forelse ($cartItems as $item)
                    @php($variant = $item['variant'])
                    @php($imageUrl = $variant->primary_image_url ?: $variant->product?->featured_image)
                    <article
                        wire:key="cart-sheet-item-{{ $item['variant_id'] }}"
                        class="space-y-3 rounded-sm border border-slate-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <div class="flex gap-3">
                            <div class="size-16 shrink-0 overflow-hidden rounded-sm border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $variant->product?->name ?? __('Product') }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ __('No image') }}</div>
                                @endif
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                    {{ $variant->product?->name ?? __('Product') }}
                                </p>
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

                        <div class="flex items-center justify-between gap-2">
                            <div class="inline-flex items-center rounded-sm border border-slate-300 dark:border-zinc-700">
                                <button
                                    type="button"
                                    wire:click="decrease({{ $item['variant_id'] }})"
                                    class="inline-flex min-h-9 min-w-9 items-center justify-center text-slate-700 transition hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                >
                                    <flux:icon.minus class="size-4" />
                                </button>
                                <span class="inline-flex min-h-9 min-w-9 items-center justify-center border-x border-slate-300 px-2 text-sm font-semibold dark:border-zinc-700">
                                    {{ $item['quantity'] }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="increase({{ $item['variant_id'] }})"
                                    class="inline-flex min-h-9 min-w-9 items-center justify-center text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                    @disabled($item['quantity'] >= $item['stock_available'])
                                >
                                    <flux:icon.plus class="size-4" />
                                </button>
                            </div>

                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                    {{ money($item['line_total'], $cartSummary['currency'])->format() }}
                                </p>
                                <button
                                    type="button"
                                    wire:click="removeItem({{ $item['variant_id'] }})"
                                    class="inline-flex size-9 items-center justify-center rounded-sm border border-rose-300 text-rose-700 transition hover:bg-rose-50 dark:border-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/20"
                                    aria-label="{{ __('Remove item') }}"
                                >
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-sm border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500 dark:border-zinc-700 dark:text-zinc-300">
                        {{ __('Your cart is empty.') }}
                    </div>
                @endforelse
            </div>

            <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-700 dark:text-zinc-200">{{ __('Subtotal') }}</p>
                    <p class="text-base font-semibold text-slate-900 dark:text-zinc-100">
                        {{ money($cartSummary['subtotal_minor'], $cartSummary['currency'])->format() }}
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <a
                        href="{{ route('storefront.cart.show') }}"
                        wire:navigate
                        class="inline-flex min-h-11 items-center justify-center rounded-sm border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        {{ __('View cart') }}
                    </a>
                    <a
                        href="{{ route('storefront.checkout.show') }}"
                        wire:navigate
                        class="inline-flex min-h-11 items-center justify-center rounded-sm border border-amber-300 bg-amber-300 px-4 text-sm font-semibold text-slate-900 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled($cartSummary['items_count'] === 0)
                    >
                        {{ __('Go to checkout') }}
                    </a>
                </div>

                <button
                    type="button"
                    wire:click="clearCart"
                    class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @disabled($cartSummary['items_count'] === 0)
                >
                    {{ __('Clear cart') }}
                </button>
            </div>
        </div>
    </flux:modal>
</div>
