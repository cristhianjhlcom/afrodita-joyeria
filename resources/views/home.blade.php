<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
    <main class="mx-auto flex min-h-screen w-full max-w-4xl items-center justify-center px-6">
        <section class="space-y-4 text-center">
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('Afrodita Joyeria Storefront') }}</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Public storefront routes will be implemented in the next phase.') }}
            </p>
        </section>
    </main>
</body>
</html>
