<?php

use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Product $product;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', [
            'title' => __('Product').' - '.$this->product->name,
        ]);
    }

    #[Computed]
    public function productDetails(): Product
    {
        return Product::query()
            ->with([
                'brand:id,name',
                'subcategory:id,name,parent_id',
                'variants' => fn ($query) => $query->withCount('images')->orderBy('id'),
                'images' => fn ($query) => $query->with('variant:id,product_id,sku,code')->orderBy('sort_order'),
            ])
            ->withCount(['variants', 'images'])
            ->findOrFail($this->product->id);
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout
        :heading="$this->productDetails->name"
        :subheading="__('Brand: :brand | Subcategory: :subcategory', ['brand' => $this->productDetails->brand?->name ?? '-', 'subcategory' => $this->productDetails->subcategory?->name ?? '-'])"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:subheading>
                    {{ __('Status: :status', ['status' => str($this->productDetails->status)->headline()]) }}
                </flux:subheading>
                <flux:button :href="route('admin.products')" variant="ghost" wire:navigate>
                    {{ __('Back to Products') }}
                </flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <flux:card>
                    <flux:heading size="sm">{{ __('Variants') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->productDetails->variants_count) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Images') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->productDetails->images_count) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Main Store ID') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ $this->productDetails->external_id ?? '-' }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Slug') }}</flux:heading>
                    <flux:text class="mt-1 text-sm font-medium">{{ $this->productDetails->slug }}</flux:text>
                </flux:card>
            </div>

            <div class="space-y-2">
                <flux:subheading>{{ __('Variants') }}</flux:subheading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('SKU') }}</flux:table.column>
                        <flux:table.column>{{ __('Color') }}</flux:table.column>
                        <flux:table.column>{{ __('Size') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Price') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Sale Price') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Stock Available') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Images') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->productDetails->variants as $variant)
                            <flux:table.row :key="$variant->id">
                                <flux:table.cell variant="strong">{{ $variant->sku ?? $variant->code ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $variant->color ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $variant->size ?? '-' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $variant->price !== null ? number_format($variant->price) : '-' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $variant->sale_price !== null ? number_format($variant->sale_price) : '-' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($variant->stock_available ?? 0) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($variant->images_count) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7">{{ __('No variants available for this product.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="space-y-2">
                <flux:subheading>{{ __('Images') }}</flux:subheading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('URL') }}</flux:table.column>
                        <flux:table.column>{{ __('Variant') }}</flux:table.column>
                        <flux:table.column>{{ __('Primary') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Sort') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->productDetails->images as $image)
                            <flux:table.row :key="$image->id">
                                <flux:table.cell>
                                    <a href="{{ $image->url }}" target="_blank" rel="noopener noreferrer" class="text-zinc-900 underline dark:text-zinc-100">
                                        {{ $image->url }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $image->variant?->sku ?? $image->variant?->code ?? '-' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($image->is_primary)
                                        <flux:badge color="green">{{ __('Yes') }}</flux:badge>
                                    @else
                                        <flux:badge>{{ __('No') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($image->sort_order ?? 0) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4">{{ __('No images available for this product.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </x-pages::admin.layout>
</section>
