<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" id="top">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <div class="flex min-h-screen flex-col">
            <header class="border-b border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8">
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-900 dark:text-zinc-100">
                        {{ config('app.name') }}
                    </p>

                    @if (! empty($cancelUrl ?? null))
                        <a
                            href="{{ $cancelUrl }}"
                            class="inline-flex min-h-10 items-center rounded-sm border border-slate-300 px-3 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            wire:navigate
                        >
                            {{ __('Cancel checkout') }}
                        </a>
                    @endif
                </div>
            </header>

            <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>

            <footer class="border-t border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mx-auto flex w-full max-w-6xl items-center justify-center px-4 py-4 text-xs text-slate-500 sm:px-6 lg:px-8 dark:text-zinc-400">
                    {{ __('Secure checkout powered by :app', ['app' => config('app.name')]) }}
                </div>
            </footer>
        </div>

        <flux:toast position="bottom end" />

        @fluxScripts
    </body>
</html>
