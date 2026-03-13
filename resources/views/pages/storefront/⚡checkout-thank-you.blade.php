<?php

use App\Concerns\PasswordValidationRules;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use PasswordValidationRules;

    public Order $order;

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $feedbackMessage = null;

    public bool $feedbackSuccess = true;

    public function mount(string $orderToken): void
    {
        $this->order = Order::query()
            ->with('items')
            ->where('order_token', $orderToken)
            ->where('source', 'local_checkout')
            ->firstOrFail();
    }

    public function rendering(View $view): void
    {
        $view->layout('layouts.storefront-checkout', [
            'title' => __('Thank you').' | '.config('app.name'),
            'cancelUrl' => route('home'),
        ]);
    }

    #[Computed]
    public function canCreateAccount(): bool
    {
        if (Auth::check()) {
            return false;
        }

        $email = trim((string) $this->order->customer_email);

        if ($email === '') {
            return false;
        }

        return ! User::query()->where('email', $email)->exists();
    }

    #[Computed]
    public function detailedItems(): Collection
    {
        $items = $this->order->items;
        $externalIds = $items
            ->pluck('variant_external_id')
            ->filter(fn (?int $id): bool => $id !== null)
            ->unique()
            ->values();

        $variants = ProductVariant::query()
            ->whereIn('external_id', $externalIds)
            ->with(['product' => fn ($query) => $query
                ->select(['id', 'name', 'featured_image'])
                ->with(['images' => fn ($imagesQuery) => $imagesQuery
                    ->select(['id', 'product_id', 'url', 'is_primary', 'sort_order'])
                    ->whereNull('deleted_at')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id')])])
            ->get(['id', 'external_id', 'product_id', 'primary_image_url', 'size', 'color'])
            ->keyBy('external_id');

        return $items->map(function (OrderItem $item) use ($variants): array {
            $variant = $item->variant_external_id ? $variants->get((int) $item->variant_external_id) : null;
            $imageUrl = $variant?->primary_image_url ?: $variant?->product?->primaryImageUrl();

            return [
                'item' => $item,
                'image_url' => $imageUrl,
            ];
        });
    }

    public function createAccount(): void
    {
        if (! $this->canCreateAccount) {
            return;
        }

        $validated = $this->validate([
            'password' => $this->passwordRules(),
        ]);

        $user = User::query()->create([
            'name' => (string) $this->order->customer_name,
            'email' => (string) $this->order->customer_email,
            'password' => $validated['password'],
        ]);

        $this->order->update([
            'user_id' => $user->id,
        ]);

        Auth::login($user);

        $this->password = '';
        $this->password_confirmation = '';
        $this->feedbackSuccess = true;
        $this->feedbackMessage = __('Your account has been created successfully.');
    }
}; ?>

<section class="space-y-6">
    <header class="space-y-3 rounded-sm border border-emerald-200 bg-emerald-50 p-6 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200">
        <div class="inline-flex size-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-200">
            <flux:icon.check class="size-6" />
        </div>
        <h1 class="text-2xl font-semibold">{{ __('Thank you for your purchase!') }}</h1>
        <p class="text-sm">{{ __('We received your payment and your order is being prepared.') }}</p>
    </header>

    @if ($feedbackMessage)
        <div class="rounded-sm border px-4 py-3 text-sm {{ $feedbackSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
            {{ $feedbackMessage }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-4 rounded-sm border border-slate-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">{{ __('Order details') }}</h2>
                <flux:badge color="emerald">{{ str($order->payment_status)->headline() }}</flux:badge>
            </div>

            <div class="space-y-2 text-sm text-slate-700 dark:text-zinc-200">
                <p><span class="font-semibold">{{ __('Order') }}:</span> #{{ $order->id }}</p>
                <p><span class="font-semibold">{{ __('Status') }}:</span> {{ str($order->status)->headline() }}</p>
                <p><span class="font-semibold">{{ __('Payment') }}:</span> {{ str((string) $order->payment_status)->headline() }}</p>
                <p><span class="font-semibold">{{ __('Customer') }}:</span> {{ $order->customer_name }}</p>
                <p><span class="font-semibold">{{ __('Email') }}:</span> {{ $order->customer_email }}</p>
            </div>

            <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-zinc-700">
                @foreach ($this->detailedItems as $row)
                    @php($item = $row['item'])
                    <article class="flex items-center gap-3 rounded-sm border border-slate-200 p-3 dark:border-zinc-700">
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
                    </article>
                @endforeach
            </div>
        </div>

        <aside class="space-y-4 rounded-sm border border-slate-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">{{ __('Summary') }}</h2>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between text-slate-600 dark:text-zinc-300">
                    <span>{{ __('Subtotal') }}</span>
                    <span>{{ money($order->subtotal, $order->currency)->format() }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-600 dark:text-zinc-300">
                    <span>{{ __('Shipping') }}</span>
                    <span>{{ money($order->shipping_total, $order->currency)->format() }}</span>
                </div>
                <div class="flex items-center justify-between text-base font-semibold text-slate-900 dark:text-zinc-100">
                    <span>{{ __('Total') }}</span>
                    <span>{{ money($order->grand_total, $order->currency)->format() }}</span>
                </div>
            </div>

            @if ($this->canCreateAccount)
                <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Create your account') }}</flux:heading>
                    <flux:text>{{ __('Set a password to manage your next orders faster.') }}</flux:text>

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <flux:input wire:model.blur="password" type="password" viewable autocomplete="new-password" />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Confirm password') }}</flux:label>
                        <flux:input wire:model.blur="password_confirmation" type="password" viewable autocomplete="new-password" />
                        <flux:error name="password_confirmation" />
                    </flux:field>

                    <flux:button variant="primary" wire:click="createAccount" wire:loading.attr="disabled" wire:target="createAccount" class="w-full">
                        {{ __('Create account') }}
                    </flux:button>
                </div>
            @elseif(auth()->check())
                <a
                    href="{{ route('settings.orders') }}"
                    wire:navigate
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-amber-300 bg-amber-300 px-4 text-sm font-semibold text-slate-900 transition hover:bg-amber-200"
                >
                    {{ __('Go to my orders') }}
                </a>
            @elseif(! auth()->check())
                <div class="space-y-2 border-t border-slate-200 pt-4 dark:border-zinc-700">
                    <flux:text>{{ __('Already have an account?') }}</flux:text>
                    <a
                        href="{{ route('login') }}"
                        wire:navigate
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        {{ __('Log in') }}
                    </a>
                    <a
                        href="{{ route('settings.orders') }}"
                        wire:navigate
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-amber-300 bg-amber-300 px-4 text-sm font-semibold text-slate-900 transition hover:bg-amber-200"
                    >
                        {{ __('Go to my orders') }}
                    </a>
                </div>
            @endif

            <a
                href="{{ route('home') }}"
                wire:navigate
                class="inline-flex min-h-11 w-full items-center justify-center rounded-sm border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ __('Continue shopping') }}
            </a>
        </aside>
    </div>
</section>
