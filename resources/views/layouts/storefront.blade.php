<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <div class="flex min-h-screen flex-col">
            <header x-data="{ mobileMenuOpen: false }" class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mx-auto flex w-full max-w-7xl items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100" wire:navigate>
                        {{ config('app.name') }}
                    </a>

                    <nav class="hidden items-center gap-5 md:flex">
                        <a
                            href="{{ route('home') }}"
                            class="text-sm font-medium text-zinc-700 transition hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100"
                            wire:navigate
                        >
                            {{ __('Catalog') }}
                        </a>
                    </nav>

                    <div class="ms-auto flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-sm border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                            aria-label="{{ __('Shopping cart') }}"
                        >
                            <flux:icon.shopping-bag class="size-4" />
                            <span>{{ __('Cart') }}</span>
                            <span class="inline-flex size-5 items-center justify-center rounded-sm border border-zinc-300 text-xs dark:border-zinc-700">0</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-sm border border-zinc-300 p-2 text-zinc-700 md:hidden dark:border-zinc-700 dark:text-zinc-200"
                            x-on:click="mobileMenuOpen = ! mobileMenuOpen"
                            aria-label="{{ __('Toggle navigation') }}"
                        >
                            <flux:icon.bars-2 class="size-5" x-show="!mobileMenuOpen" />
                            <flux:icon.x-mark class="size-5" x-show="mobileMenuOpen" x-cloak />
                        </button>
                    </div>
                </div>

                <div class="border-t border-zinc-200 px-4 py-3 md:hidden dark:border-zinc-800" x-show="mobileMenuOpen" x-cloak>
                    <a
                        href="{{ route('home') }}"
                        class="block rounded-sm border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-800 dark:border-zinc-700 dark:text-zinc-200"
                        wire:navigate
                    >
                        {{ __('Catalog') }}
                    </a>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>

            <footer class="border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8">
                    <div class="space-y-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-900 dark:text-zinc-100">{{ config('app.name') }}</h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('Jewelry and curated catalog synced from the main store.') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-900 dark:text-zinc-100">{{ __('Quick Links') }}</h2>
                        <ul class="space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                            <li>
                                <a href="{{ route('home') }}" class="hover:text-zinc-900 dark:hover:text-zinc-100" wire:navigate>{{ __('Catalog') }}</a>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-900 dark:text-zinc-100">{{ __('Customer Care') }}</h2>
                        <ul class="space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                            <li>{{ __('Shipping information') }}</li>
                            <li>{{ __('Returns and exchanges') }}</li>
                            <li>{{ __('Support contact') }}</li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-zinc-200 px-4 py-4 text-center text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    {{ __('© :year :name. All rights reserved.', ['year' => now()->year, 'name' => config('app.name')]) }}
                </div>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
