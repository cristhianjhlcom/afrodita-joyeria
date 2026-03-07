@props([
    'href' => null,
    'imageClass' => 'h-8 w-auto',
    'alt' => null,
])

@php
    $altText = $alt ?? config('app.name');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('inline-flex items-center') }} wire:navigate>
        <img src="{{ asset('assets/images/afrodita_logo.png') }}" alt="{{ $altText }}" class="{{ $imageClass }}">
    </a>
@else
    <img
        src="{{ asset('assets/images/afrodita_logo.png') }}"
        alt="{{ $altText }}"
        {{ $attributes->class($imageClass) }}
    >
@endif
