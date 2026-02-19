@props([
    'heading' => null,
    'subheading' => null,
])

<div class="flex w-full max-w-7xl flex-1 flex-col gap-6">
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
