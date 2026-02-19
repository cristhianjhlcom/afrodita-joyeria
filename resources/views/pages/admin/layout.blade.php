@props([
    'heading' => null,
    'subheading' => null,
])

<div class="flex w-full max-w-7xl flex-1 flex-col gap-6">
    <div class="grid gap-6 lg:grid-cols-[250px_1fr]">
        <aside class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Admin') }}</flux:heading>
            <flux:navlist aria-label="{{ __('Admin Navigation') }}" class="mt-4">
                <flux:navlist.item :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('admin.brands')" :current="request()->routeIs('admin.brands')" wire:navigate>
                    {{ __('Brands') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('admin.categories')" :current="request()->routeIs('admin.categories')" wire:navigate>
                    {{ __('Categories') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('admin.products')" :current="request()->routeIs('admin.products')" wire:navigate>
                    {{ __('Products') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('admin.inventory')" :current="request()->routeIs('admin.inventory')" wire:navigate>
                    {{ __('Inventory') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('admin.orders')" :current="request()->routeIs('admin.orders')" wire:navigate>
                    {{ __('Orders') }}
                </flux:navlist.item>
            </flux:navlist>
        </aside>

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="xl">{{ $heading }}</flux:heading>
                <flux:subheading>{{ $subheading }}</flux:subheading>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                {{ $slot }}
            </div>
        </section>
    </div>
</div>
