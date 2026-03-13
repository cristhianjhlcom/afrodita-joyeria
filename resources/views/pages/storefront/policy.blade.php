@component('layouts.storefront')
    <section class="mx-auto w-full max-w-3xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                {{ $title }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-zinc-300">
                {{ __('Informacion legal para compras seguras en Afrodita Joyeria.') }}
            </p>
        </header>

        <article class="prose prose-slate max-w-none dark:prose-invert">
            {!! $content !!}
        </article>
    </section>
@endcomponent
