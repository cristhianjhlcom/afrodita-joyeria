<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CartService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Product $product;

    public ?string $selectedSize = null;

    public ?string $selectedColorKey = null;

    public int $activeImageIndex = 0;

    public ?string $cartFeedbackMessage = null;

    public bool $cartFeedbackSuccess = true;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->initializeVariantSelection();
    }

    public function rendering(View $view): void
    {
        $product = $this->productDetails;
        $title = $product->name.' | '.config('app.name');
        $metaDescription = $this->metaDescription();
        $canonicalUrl = route('storefront.products.show', $product);
        $ogImage = $this->galleryImages->first()['url'] ?? null;

        $view->layout('layouts.storefront', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'metaRobots' => 'index,follow',
            'ogTitle' => $title,
            'ogDescription' => $metaDescription,
            'ogType' => 'product',
            'ogUrl' => $canonicalUrl,
            'ogImage' => $ogImage,
            'structuredDataJson' => $this->structuredDataJson($canonicalUrl, $ogImage),
        ]);
    }

    #[Computed]
    public function productDetails(): Product
    {
        return Product::query()
            ->whereNull('deleted_at')
            ->with([
                'brand:id,name',
                'category:id,name',
                'subcategory:id,name,category_id',
                'images' => fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->orderBy('size')
                    ->orderBy('color')
                    ->with(['images' => fn ($imagesQuery) => $imagesQuery
                        ->whereNull('deleted_at')
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->orderBy('id')]),
            ])
            ->findOrFail($this->product->id);
    }

    #[Computed]
    public function availableVariants(): Collection
    {
        /** @var Collection<int, ProductVariant> $variants */
        $variants = $this->productDetails->variants;

        return $variants->values();
    }

    #[Computed]
    public function availableSizes(): Collection
    {
        return $this->availableVariants
            ->map(fn (ProductVariant $variant): string => trim((string) ($variant->size ?: __('One Size'))))
            ->filter(fn (string $size): bool => $size !== '')
            ->unique()
            ->values();
    }

    #[Computed]
    public function colorsForSelectedSize(): Collection
    {
        if ($this->selectedSize === null) {
            return collect();
        }

        return $this->availableVariants
            ->filter(fn (ProductVariant $variant): bool => $this->variantSizeLabel($variant) === $this->selectedSize)
            ->groupBy(fn (ProductVariant $variant): string => $this->variantColorKey($variant))
            ->map(function (Collection $variants, string $colorKey): array {
                /** @var ProductVariant $first */
                $first = $variants->first();

                return [
                    'key' => $colorKey,
                    'label' => $this->variantColorLabel($first),
                    'hex' => $this->variantHexColor($first),
                    'in_stock' => $variants->contains(fn (ProductVariant $variant): bool => (int) $variant->stock_available > 0),
                ];
            })
            ->values();
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        if ($this->selectedSize === null || $this->selectedColorKey === null) {
            return null;
        }

        /** @var ProductVariant|null $variant */
        $variant = $this->availableVariants
            ->first(fn (ProductVariant $productVariant): bool => $this->variantSizeLabel($productVariant) === $this->selectedSize
                && $this->variantColorKey($productVariant) === $this->selectedColorKey);

        return $variant;
    }

    #[Computed]
    public function galleryImages(): Collection
    {
        $selectedVariant = $this->selectedVariant;
        $images = collect();

        if ($selectedVariant !== null && $selectedVariant->images->isNotEmpty()) {
            $images = $images->merge(
                $selectedVariant->images->map(fn ($image): array => [
                    'url' => (string) $image->url,
                    'alt' => $this->imageAlt(
                        (string) ($image->alt ?? ''),
                        $selectedVariant
                    ),
                ])
            );
        }

        if ($this->productDetails->images->isNotEmpty()) {
            $images = $images->merge(
                $this->productDetails->images->map(fn ($image): array => [
                    'url' => (string) $image->url,
                    'alt' => $this->imageAlt((string) ($image->alt ?? '')),
                ])
            );
        }

        $featuredImage = trim((string) ($this->productDetails->featured_image ?? ''));
        if ($images->isEmpty() && $featuredImage !== '') {
            $images = collect([[
                'url' => $featuredImage,
                'alt' => $this->productDetails->name,
            ]]);
        }

        return $images
            ->filter(fn (array $image): bool => trim($image['url']) !== '')
            ->unique('url')
            ->values();
    }

    #[Computed]
    public function activeImage(): array
    {
        $images = $this->galleryImages;

        if ($images->isEmpty()) {
            return [
                'url' => null,
                'alt' => $this->productDetails->name,
            ];
        }

        $index = min(max($this->activeImageIndex, 0), $images->count() - 1);

        return $images->get($index);
    }

    #[Computed]
    public function selectedPriceData(): array
    {
        $selectedVariant = $this->selectedVariant;

        if ($selectedVariant !== null) {
            return [
                'current' => $selectedVariant->price,
                'original' => null,
            ];
        }

        $fallback = $this->availableVariants
            ->map(fn (ProductVariant $variant): ?int => $variant->price)
            ->filter(fn (?int $amount): bool => $amount !== null)
            ->min();

        return [
            'current' => $fallback,
            'original' => null,
        ];
    }

    #[Computed]
    public function isAvailable(): bool
    {
        return $this->selectedVariant !== null && (int) $this->selectedVariant->stock_available > 0;
    }

    public function selectSize(string $size): void
    {
        $this->selectedSize = $size;
        $availableColorKeys = $this->colorsForSelectedSize->pluck('key')->all();

        if ($this->selectedColorKey === null || ! in_array($this->selectedColorKey, $availableColorKeys, true)) {
            $this->selectedColorKey = $availableColorKeys[0] ?? null;
        }

        $this->activeImageIndex = 0;
    }

    public function selectColor(string $colorKey): void
    {
        if (! $this->colorsForSelectedSize->contains(fn (array $color): bool => $color['key'] === $colorKey)) {
            return;
        }

        $this->selectedColorKey = $colorKey;
        $this->activeImageIndex = 0;
    }

    public function nextImage(): void
    {
        $count = $this->galleryImages->count();
        if ($count <= 1) {
            return;
        }

        $this->activeImageIndex = ($this->activeImageIndex + 1) % $count;
    }

    public function previousImage(): void
    {
        $count = $this->galleryImages->count();
        if ($count <= 1) {
            return;
        }

        $this->activeImageIndex = ($this->activeImageIndex - 1 + $count) % $count;
    }

    public function selectImage(int $index): void
    {
        $count = $this->galleryImages->count();
        if ($count === 0) {
            return;
        }

        $this->activeImageIndex = min(max($index, 0), $count - 1);
    }

    public function addToCart(): void
    {
        if (! $this->isAvailable || $this->selectedVariant === null) {
            $this->cartFeedbackSuccess = false;
            $this->cartFeedbackMessage = __('This variant is out of stock.');
            $this->dispatch('toast-show',
                duration: 3500,
                slots: ['heading' => __('Cart'), 'text' => $this->cartFeedbackMessage],
                dataset: ['variant' => 'warning']
            );

            return;
        }

        $result = app(CartService::class)->addVariant((int) $this->selectedVariant->id);

        $this->cartFeedbackSuccess = (bool) $result['ok'];
        $this->cartFeedbackMessage = (string) $result['message'];

        if ($result['ok']) {
            $this->dispatch('cart-updated');
        }

        $this->dispatch('toast-show',
            duration: 3500,
            slots: ['heading' => __('Cart'), 'text' => $this->cartFeedbackMessage],
            dataset: ['variant' => $result['ok'] ? 'success' : 'warning']
        );
    }

    public function formatMinorAmount(?int $amount): string
    {
        if ($amount === null) {
            return __('Price unavailable');
        }

        return money($amount, config('services.main_store.currency', 'PEN'))->format();
    }

    protected function initializeVariantSelection(): void
    {
        $preferredSize = $this->availableVariants
            ->filter(fn (ProductVariant $variant): bool => (int) $variant->stock_available > 0)
            ->map(fn (ProductVariant $variant): string => $this->variantSizeLabel($variant))
            ->first();

        $this->selectedSize = $preferredSize
            ?? $this->availableSizes->first();

        if ($this->selectedSize !== null) {
            $firstColorKey = $this->colorsForSelectedSize->first()['key'] ?? null;
            $this->selectedColorKey = is_string($firstColorKey) ? $firstColorKey : null;
        }
    }

    protected function variantColorKey(ProductVariant $variant): string
    {
        $color = strtolower(trim((string) ($variant->color ?: 'default')));
        $hex = strtolower(trim((string) ($variant->hex ?: 'default')));

        return $color.'|'.$hex;
    }

    protected function variantColorLabel(ProductVariant $variant): string
    {
        return trim((string) ($variant->color ?: __('Default')));
    }

    protected function variantHexColor(ProductVariant $variant): ?string
    {
        $hex = trim((string) ($variant->hex ?? ''));

        return $hex !== '' ? $hex : null;
    }

    protected function variantSizeLabel(ProductVariant $variant): string
    {
        return trim((string) ($variant->size ?: __('One Size')));
    }

    protected function imageAlt(string $alt, ?ProductVariant $variant = null): string
    {
        if (trim($alt) !== '') {
            return $alt;
        }

        if ($variant !== null) {
            return "{$this->productDetails->name} - {$this->variantColorLabel($variant)} - {$this->variantSizeLabel($variant)}";
        }

        return $this->productDetails->name;
    }

    protected function metaDescription(): string
    {
        $description = trim(strip_tags((string) ($this->productDetails->description ?? '')));
        if ($description !== '') {
            return str($description)->limit(160)->toString();
        }

        return str(__('Buy :product with secure checkout and fast shipping.', ['product' => $this->productDetails->name]))
            ->limit(160)
            ->toString();
    }

    protected function structuredDataJson(string $url, ?string $imageUrl): string
    {
        $priceAmount = $this->selectedPriceData['current'];
        $availability = $this->isAvailable
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->productDetails->name,
            'description' => $this->metaDescription(),
            'sku' => $this->selectedVariant?->sku ?? $this->selectedVariant?->code,
            'image' => $this->galleryImages->pluck('url')->all(),
            'brand' => $this->productDetails->brand?->name
                ? ['@type' => 'Brand', 'name' => $this->productDetails->brand->name]
                : null,
            'offers' => $priceAmount !== null
                ? [
                    '@type' => 'Offer',
                    'price' => number_format($priceAmount / 100, 2, '.', ''),
                    'priceCurrency' => config('services.main_store.currency', 'PEN'),
                    'availability' => $availability,
                    'url' => $url,
                    'itemCondition' => 'https://schema.org/NewCondition',
                ]
                : null,
            'url' => $url,
        ];

        if ($imageUrl !== null && $payload['image'] === []) {
            $payload['image'] = [$imageUrl];
        }

        return (string) json_encode(
            array_filter($payload, fn (mixed $value): bool => $value !== null && $value !== []),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}; ?>

<section class="space-y-8">
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-slate-600 dark:text-zinc-300">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" class="hover:text-slate-900 dark:hover:text-zinc-100" wire:navigate>{{ __('Inicio') }}</a></li>
            <li aria-hidden="true">/</li>
            @if ($this->productDetails->category)
                <li>
                    <a
                        href="{{ route('home', ['cats' => [$this->productDetails->category->id]]) }}"
                        class="hover:text-slate-900 dark:hover:text-zinc-100"
                        wire:navigate
                    >
                        {{ $this->productDetails->category->name }}
                    </a>
                </li>
                <li aria-hidden="true">/</li>
            @endif
            <li class="font-medium text-slate-900 dark:text-zinc-100">{{ $this->productDetails->name }}</li>
        </ol>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,54%)_minmax(0,46%)]">
        <div class="space-y-4">
            <div
                class="relative overflow-hidden rounded-sm border border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                tabindex="0"
                x-on:keydown.right.prevent="$wire.nextImage()"
                x-on:keydown.left.prevent="$wire.previousImage()"
                aria-label="{{ __('Product image carousel') }}"
            >
                <div class="aspect-square">
                    @if ($this->activeImage['url'])
                        <img
                            src="{{ $this->activeImage['url'] }}"
                            alt="{{ $this->activeImage['alt'] }}"
                            class="h-full w-full object-cover"
                            loading="eager"
                            onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('hidden');"
                        >
                        <div class="hidden h-full items-center justify-center text-sm text-slate-500 dark:text-zinc-300">{{ __('Image unavailable') }}</div>
                    @else
                        <div class="flex h-full items-center justify-center text-sm text-slate-500 dark:text-zinc-300">{{ __('Image unavailable') }}</div>
                    @endif
                </div>

                @if ($this->galleryImages->count() > 1)
                    <button
                        type="button"
                        wire:click="previousImage"
                        class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full border border-slate-300 bg-white/95 p-2 text-slate-800 shadow transition hover:border-slate-500 dark:border-zinc-600 dark:bg-zinc-900/95 dark:text-zinc-100"
                        aria-label="{{ __('Previous image') }}"
                    >
                        <flux:icon.chevron-left class="size-4" />
                    </button>
                    <button
                        type="button"
                        wire:click="nextImage"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full border border-slate-300 bg-white/95 p-2 text-slate-800 shadow transition hover:border-slate-500 dark:border-zinc-600 dark:bg-zinc-900/95 dark:text-zinc-100"
                        aria-label="{{ __('Next image') }}"
                    >
                        <flux:icon.chevron-right class="size-4" />
                    </button>
                @endif
            </div>

            @if ($this->galleryImages->isNotEmpty())
                <div class="grid grid-cols-5 gap-2 sm:grid-cols-6">
                    @foreach ($this->galleryImages as $index => $image)
                        <button
                            type="button"
                            wire:key="product-detail-image-{{ $index }}"
                            wire:click="selectImage({{ $index }})"
                            class="aspect-square overflow-hidden rounded-sm border {{ $activeImageIndex === $index ? 'border-slate-900 ring-1 ring-slate-900 dark:border-zinc-100 dark:ring-zinc-100' : 'border-slate-200 dark:border-zinc-700' }}"
                            aria-label="{{ __('View image :number', ['number' => $index + 1]) }}"
                            aria-current="{{ $activeImageIndex === $index ? 'true' : 'false' }}"
                        >
                            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" class="h-full w-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <article class="self-start space-y-5 rounded-sm border border-slate-200 bg-white p-5 lg:sticky lg:top-28 dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <header class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-zinc-400">
                    {{ $this->productDetails->brand?->name ?? __('Brand unavailable') }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">{{ $this->productDetails->name }}</h1>
            </header>

            <div class="space-y-1">
                <p class="text-2xl font-bold text-slate-900 dark:text-zinc-100">
                    {{ $this->formatMinorAmount($this->selectedPriceData['current']) }}
                </p>
                @if ($this->selectedPriceData['original'] !== null)
                    <p class="text-sm text-slate-500 line-through dark:text-zinc-400">
                        {{ $this->formatMinorAmount($this->selectedPriceData['original']) }}
                    </p>
                @endif
                <p class="text-sm {{ $this->isAvailable ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                    {{ $this->isAvailable ? __('In stock') : __('Out of stock') }}
                </p>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-700 dark:text-zinc-200">{{ __('Size') }}</p>
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Select size') }}">
                    @forelse ($this->availableSizes as $size)
                        <button
                            type="button"
                            wire:key="product-detail-size-{{ md5($size) }}"
                            wire:click="selectSize('{{ addslashes($size) }}')"
                            aria-pressed="{{ $selectedSize === $size ? 'true' : 'false' }}"
                            class="inline-flex min-h-10 items-center rounded-sm border px-3 text-sm font-medium transition {{ $selectedSize === $size ? 'border-slate-900 bg-slate-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-slate-300 bg-white text-slate-800 hover:border-slate-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-zinc-400' }}"
                        >
                            {{ $size }}
                        </button>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-zinc-400">{{ __('No sizes available') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-700 dark:text-zinc-200">{{ __('Color') }}</p>
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Select color') }}">
                    @forelse ($this->colorsForSelectedSize as $color)
                        <button
                            type="button"
                            wire:key="product-detail-color-{{ md5($color['key']) }}"
                            wire:click="selectColor('{{ addslashes($color['key']) }}')"
                            aria-pressed="{{ $selectedColorKey === $color['key'] ? 'true' : 'false' }}"
                            class="inline-flex min-h-10 items-center gap-2 rounded-sm border px-3 text-sm font-medium transition {{ $selectedColorKey === $color['key'] ? 'border-slate-900 bg-slate-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-slate-300 bg-white text-slate-800 hover:border-slate-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-zinc-400' }}"
                        >
                            <span
                                class="inline-flex size-4 rounded-full border border-slate-300 dark:border-zinc-500"
                                style="background-color: {{ $color['hex'] ?? '#d4d4d8' }}"
                                aria-hidden="true"
                            ></span>
                            <span>{{ $color['label'] }}</span>
                            @if (! $color['in_stock'])
                                <span class="text-[11px] font-semibold uppercase tracking-wide">{{ __('No stock') }}</span>
                            @endif
                        </button>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-zinc-400">{{ __('No colors available for this size') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-sm border border-amber-300 bg-amber-300 px-4 text-sm font-semibold text-slate-900 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-200 disabled:text-slate-500 dark:disabled:border-zinc-700 dark:disabled:bg-zinc-700 dark:disabled:text-zinc-400"
                    @disabled(! $this->isAvailable)
                    aria-disabled="{{ $this->isAvailable ? 'false' : 'true' }}"
                >
                    {{ __('Buy') }}
                </button>
                <button
                    type="button"
                    wire:click="addToCart"
                    class="inline-flex min-h-11 items-center justify-center rounded-sm border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-200 disabled:text-slate-500 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300 dark:disabled:border-zinc-700 dark:disabled:bg-zinc-700 dark:disabled:text-zinc-400"
                    @disabled(! $this->isAvailable)
                    aria-disabled="{{ $this->isAvailable ? 'false' : 'true' }}"
                >
                    {{ __('Add to Cart') }}
                </button>
            </div>

            @if ($cartFeedbackMessage)
                <p class="text-sm {{ $cartFeedbackSuccess ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                    {{ $cartFeedbackMessage }}
                </p>
            @endif

            <div class="space-y-2 border-t border-slate-200 pt-4 text-sm text-slate-600 dark:border-zinc-700 dark:text-zinc-300">
                <p>{{ __('Secure checkout with protected payment flow.') }}</p>
                <p>{{ __('Fast dispatch for available variants.') }}</p>
                <p>{{ __('Need help? Contact support before purchasing.') }}</p>
            </div>
        </article>
    </div>

    @if (trim((string) $this->productDetails->description) !== '')
        <section class="space-y-2 rounded-sm border border-slate-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">{{ __('Product details') }}</h2>
            <div class="space-y-4 leading-7 text-slate-700 dark:text-zinc-300 [&_a]:text-slate-900 [&_a]:underline [&_strong]:font-semibold dark:[&_a]:text-zinc-100">
                {!! $this->productDetails->description !!}
            </div>
        </section>
    @endif
</section>
