<header class="sticky top-0 z-50 border-b border-slate-700 shadow-md">
    <div class="bg-slate-900 text-slate-100">
        <div class="mx-auto w-full max-w-screen-2xl px-3 py-2 sm:px-4">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a
                    href="{{ route('home') }}"
                    class="order-1 inline-flex min-h-11 items-center rounded-sm border border-slate-700 px-3 text-sm font-semibold tracking-wide text-white transition hover:border-amber-400 hover:text-amber-300"
                    wire:navigate
                >
                    {{ config('app.name') }}
                </a>

                <div class="order-2 ms-auto flex items-center gap-2 md:order-3">
                    <livewire:storefront.cart-sheet />

                    @auth
                        <flux:dropdown position="bottom" align="end">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-1.5 rounded-sm border border-slate-600 bg-slate-800 px-3 text-xs font-semibold uppercase tracking-wide text-slate-100 transition hover:border-amber-400 hover:text-amber-300 sm:text-sm"
                            >
                                <flux:icon.user class="size-4" />
                                <span>{{ __('Mi cuenta') }}</span>
                                <flux:icon.chevron-down class="size-3.5" />
                            </button>

                            <flux:menu class="min-w-64">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                    </div>
                                </div>

                                <flux:menu.separator />

                                <flux:menu.item :href="route('settings.orders')" icon="archive-box" wire:navigate>
                                    {{ __('Mis pedidos') }}
                                </flux:menu.item>
                                <flux:menu.item :href="route('profile.edit')" icon="user-circle" wire:navigate>
                                    {{ __('Perfil') }}
                                </flux:menu.item>
                                <flux:menu.item :href="route('user-password.edit')" icon="key" wire:navigate>
                                    {{ __('Contraseña') }}
                                </flux:menu.item>
                                <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>
                                    {{ __('Apariencia') }}
                                </flux:menu.item>

                                <flux:menu.separator />

                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item
                                        as="button"
                                        type="submit"
                                        icon="arrow-right-start-on-rectangle"
                                        class="w-full cursor-pointer"
                                        data-test="storefront-logout-button"
                                    >
                                        {{ __('Cerrar sesión') }}
                                    </flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-sm border border-slate-600 bg-slate-800 px-3 text-xs font-semibold uppercase tracking-wide text-slate-100 transition hover:border-amber-400 hover:text-amber-300 sm:text-sm"
                            wire:navigate
                        >
                            <flux:icon.user class="size-4" />
                            <span>{{ __('Iniciar sesión') }}</span>
                        </a>
                    @endauth
                </div>

                <form
                    method="GET"
                    action="{{ route('home') }}"
                    class="order-3 flex min-w-0 basis-full items-stretch md:order-2 md:flex-1"
                >
                    <label for="storefront-header-search" class="sr-only">{{ __('Search products') }}</label>
                    <input
                        id="storefront-header-search"
                        type="search"
                        name="q"
                        value="{{ $searchTerm }}"
                        placeholder="{{ __('Buscar productos, marcas y más') }}"
                        class="min-h-11 w-full rounded-s-sm border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none ring-0 placeholder:text-slate-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-400"
                    >
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center rounded-e-sm border border-amber-400 bg-amber-400 px-3 text-slate-900 transition hover:bg-amber-300 sm:px-4"
                        aria-label="{{ __('Search') }}"
                    >
                        <flux:icon.magnifying-glass class="size-4" />
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-700 bg-slate-800 text-slate-100">
        <nav class="mx-auto w-full max-w-screen-2xl px-3 sm:px-4" aria-label="{{ __('Category navigation') }}">
            <div class="flex items-center gap-1 overflow-x-auto py-2 whitespace-nowrap">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex min-h-9 items-center rounded-sm px-3 text-sm font-medium transition hover:bg-slate-700 hover:text-amber-300"
                    wire:navigate
                >
                    {{ __('Todo') }}
                </a>

                @foreach ($categories as $category)
                    <a
                        href="{{ route('home', ['cats' => [$category->id]]) }}"
                        class="inline-flex min-h-9 items-center rounded-sm px-3 text-sm font-medium transition hover:bg-slate-700 hover:text-amber-300"
                        wire:key="header-category-{{ $category->id }}"
                        wire:navigate
                    >
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</header>
