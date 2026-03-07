@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name')" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center">
            <x-brand-logo image-class="h-6 w-auto" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name')" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center">
            <x-brand-logo image-class="h-7 w-auto" />
        </x-slot>
    </flux:brand>
@endif
