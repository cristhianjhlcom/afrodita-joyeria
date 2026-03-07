<footer class="mt-12 border-t border-slate-200 bg-slate-900 text-slate-200">
    <div class="mx-auto w-full max-w-screen-2xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-white">{{ config('app.name') }}</h2>
                <p class="text-sm leading-6 text-slate-300">
                    {{ __('Joyería seleccionada para compras rápidas, seguras y con envíos a todo el país.') }}
                </p>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-white">{{ __('Compras') }}</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>
                        <a href="{{ route('home') }}" class="transition hover:text-amber-300" wire:navigate>{{ __('Catálogo') }}</a>
                    </li>
                    <li>{{ __('Novedades') }}</li>
                    <li>{{ __('Más vendidos') }}</li>
                    <li>{{ __('Promociones') }}</li>
                </ul>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-white">{{ __('Atención al cliente') }}</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>{{ __('Seguimiento de pedidos') }}</li>
                    <li>{{ __('Cambios y devoluciones') }}</li>
                    <li>{{ __('Opciones de envío') }}</li>
                    <li>{{ __('Centro de ayuda') }}</li>
                </ul>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-white">{{ __('Legal') }}</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>{{ __('Términos y condiciones') }}</li>
                    <li>{{ __('Política de privacidad') }}</li>
                    <li>{{ __('Política de cookies') }}</li>
                    <li>{{ __('Libro de reclamaciones') }}</li>
                </ul>
            </section>
        </div>
    </div>

    <div class="border-t border-slate-700 bg-slate-950">
        <div class="mx-auto flex w-full max-w-screen-2xl flex-col gap-3 px-4 py-4 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>{{ __('© :year :name. Todos los derechos reservados.', ['year' => now()->year, 'name' => config('app.name')]) }}</p>
            <a href="#top" class="inline-flex items-center gap-1 font-medium text-slate-300 transition hover:text-amber-300">
                <flux:icon.chevron-up class="size-4" />
                <span>{{ __('Volver arriba') }}</span>
            </a>
        </div>
    </div>
</footer>
