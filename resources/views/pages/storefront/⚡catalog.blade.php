<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CartService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> */
    #[Url(as: 'cats')]
    public array $categories = [];

    /** @var list<string> */
    #[Url(as: 'subs')]
    public array $subcategories = [];

    #[Url(as: 'min')]
    public int $priceMin = 0;

    #[Url(as: 'max')]
    public int $priceMax = 0;

    public ?string $cartFeedbackMessage = null;

    public bool $cartFeedbackSuccess = true;

    public function rendering(View $view): void
    {
        $view->layout('layouts.storefront', [
            'title' => __('Catalog').' | '.config('app.name'),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategories(): void
    {
        $this->resetPage();
    }

    public function updatedSubcategories(): void
    {
        $this->resetPage();
    }

    public function updatedPriceMin(): void
    {
        $this->resetPage();
    }

    public function updatedPriceMax(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function categoryTree()
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function mount(): void
    {
        $bounds = $this->priceBounds;

        if ($this->priceMin < $bounds['min']) {
            $this->priceMin = $bounds['min'];
        }

        if ($this->priceMax <= 0 || $this->priceMax > $bounds['max']) {
            $this->priceMax = $bounds['max'];
        }
    }

    #[Computed]
    public function priceBounds(): array
    {
        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->whereNull('deleted_at'))
            ->whereNotNull('price')
            ->get(['price']);

        $effectivePrices = $variants
            ->map(fn (ProductVariant $variant): ?int => $variant->price)
            ->filter(fn (?int $amount): bool => $amount !== null)
            ->values();

        $min = (int) ($effectivePrices->min() ?? 0);
        $max = (int) ($effectivePrices->max() ?? 200_000);

        return [
            'min' => $min,
            'max' => max($min, $max),
        ];
    }

    #[Computed]
    public function products()
    {
        $searchTerm = trim($this->search);
        $selectedCategories = collect($this->categories)
            ->map(fn (string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $selectedSubcategories = collect($this->subcategories)
            ->map(fn (string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $bounds = $this->priceBounds;
        $effectiveMin = max($bounds['min'], min($this->priceMin, $this->priceMax));
        $effectiveMax = max($this->priceMin, $this->priceMax);
        $hasPriceRangeFilter = $effectiveMin > $bounds['min'] || $effectiveMax < $bounds['max'];
        $hasActiveFilters = $searchTerm !== '' || $selectedCategories !== [] || $selectedSubcategories !== [] || $hasPriceRangeFilter;

        $baseQuery = Product::query()
            ->with([
                'brand:id,name',
                'category:id,name',
                'subcategory:id,name,parent_id',
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'url', 'is_primary', 'sort_order'])
                    ->whereNull('variant_id')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order'),
                'variants' => fn ($query) => $query
                    ->select(['id', 'product_id', 'price', 'stock_available', 'is_active'])
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ])
            ->whereNull('deleted_at');

        $filteredQuery = (clone $baseQuery)
            ->when($searchTerm !== '', fn (Builder $query): Builder => $query->where('name', 'like', "%{$searchTerm}%"))
            ->when($selectedCategories !== [], fn (Builder $query): Builder => $query->whereIn('category_id', $selectedCategories))
            ->when($selectedSubcategories !== [], fn (Builder $query): Builder => $query->whereIn('subcategory_id', $selectedSubcategories))
            ->when($hasPriceRangeFilter, function (Builder $query) use ($effectiveMin, $effectiveMax): void {
                $query->whereHas('variants', function (Builder $variantQuery) use ($effectiveMin, $effectiveMax): void {
                    $variantQuery
                        ->whereNull('deleted_at')
                        ->where('is_active', true)
                        ->where('price', '>=', $effectiveMin)
                        ->where('price', '<=', $effectiveMax);
                });
            });

        if ($hasActiveFilters && ! (clone $filteredQuery)->exists()) {
            return $baseQuery
                ->latest('updated_at')
                ->paginate(20);
        }

        return $filteredQuery
            ->latest('updated_at')
            ->paginate(20);
    }

    public function productCardPrice(Product $product): ?int
    {
        return $product->variants
            ->map(fn (ProductVariant $variant): ?int => $variant->price)
            ->filter(fn (?int $amount): bool => $amount !== null)
            ->min();
    }

    public function productCardImageUrl(Product $product): ?string
    {
        $featuredImage = trim((string) ($product->featured_image ?? ''));
        if ($featuredImage !== '') {
            return $featuredImage;
        }

        return $product->images->first()?->url;
    }

    public function hasStock(Product $product): bool
    {
        return $product->variants->contains(fn (ProductVariant $variant): bool => (int) $variant->stock_available > 0);
    }

    public function addToCart(int $productId): void
    {
        $product = Product::query()
            ->whereNull('deleted_at')
            ->with([
                'variants' => fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->orderBy('price')
                    ->orderBy('id'),
            ])
            ->find($productId);

        if (! $product) {
            $this->cartFeedbackSuccess = false;
            $this->cartFeedbackMessage = __('Product is no longer available.');
            $this->dispatch('toast-show',
                duration: 3500,
                slots: ['heading' => __('Cart'), 'text' => $this->cartFeedbackMessage],
                dataset: ['variant' => 'warning']
            );

            return;
        }

        /** @var ProductVariant|null $variant */
        $variant = $product->variants
            ->first(fn (ProductVariant $productVariant): bool => (int) $productVariant->stock_available > 0);

        if (! $variant) {
            $this->cartFeedbackSuccess = false;
            $this->cartFeedbackMessage = __('This product is out of stock.');
            $this->dispatch('toast-show',
                duration: 3500,
                slots: ['heading' => __('Cart'), 'text' => $this->cartFeedbackMessage],
                dataset: ['variant' => 'warning']
            );

            return;
        }

        $result = app(CartService::class)->addVariant((int) $variant->id);
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

    public function formattedMajorAmount(int $amount): string
    {
        return number_format($amount / 100, 2);
    }
}; ?>

<section class="space-y-6">
    <header class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Catalog') }}</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Browse synced products from the main store.') }}</p>
    </header>

    @if ($cartFeedbackMessage)
        <div class="rounded-sm border px-4 py-3 text-sm {{ $cartFeedbackSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
            {{ $cartFeedbackMessage }}
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="self-start space-y-6 rounded-sm border border-zinc-200 bg-white p-4 lg:sticky lg:top-32 lg:max-h-[calc(100vh-9rem)] lg:overflow-auto dark:border-zinc-700 dark:bg-zinc-900">
            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Search') }}</h2>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search product name...') }}"
                    class="w-full rounded-sm border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                >
            </div>

            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Price') }}</h2>
                <div class="rounded-sm border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="mb-3 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Min') }}: {{ $this->formattedMajorAmount($priceMin) }}</span>
                        <span>{{ __('Max') }}: {{ $this->formattedMajorAmount($priceMax) }}</span>
                    </div>
                    <div class="space-y-3">
                        <input
                            type="range"
                            wire:model.live="priceMin"
                            min="{{ $this->priceBounds['min'] }}"
                            max="{{ $this->priceBounds['max'] }}"
                            class="w-full accent-zinc-900 dark:accent-zinc-100"
                        >
                        <input
                            type="range"
                            wire:model.live="priceMax"
                            min="{{ $this->priceBounds['min'] }}"
                            max="{{ $this->priceBounds['max'] }}"
                            class="w-full accent-zinc-900 dark:accent-zinc-100"
                        >
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Categories') }}</h2>
                <div class="max-h-72 space-y-3 overflow-auto pr-1">
                    @forelse ($this->categoryTree as $category)
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" wire:model.live="categories" value="{{ $category->id }}" class="rounded-sm border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800">
                                <span>{{ $category->name }}</span>
                            </label>

                            @if ($category->children->isNotEmpty())
                                <div class="ml-5 space-y-2">
                                    @foreach ($category->children as $subcategory)
                                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                            <input type="checkbox" wire:model.live="subcategories" value="{{ $subcategory->id }}" class="rounded-sm border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800">
                                            <span>{{ $subcategory->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-zinc-500">{{ __('No categories available') }}</p>
                    @endforelse
                </div>
            </div>
        </aside>

        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($this->products as $product)
                    @php($productDetailUrl = route('storefront.products.show', $product))
                    <article
                        class="overflow-hidden rounded-sm border border-zinc-200 bg-white transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                        role="link"
                        tabindex="0"
                        x-data
                        x-on:click="window.location.href = '{{ $productDetailUrl }}'"
                        x-on:keydown.enter.prevent="window.location.href = '{{ $productDetailUrl }}'"
                        x-on:keydown.space.prevent="window.location.href = '{{ $productDetailUrl }}'"
                    >
                        <div class="aspect-square overflow-hidden rounded-sm border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($this->productCardImageUrl($product))
                                <img
                                    src="{{ $this->productCardImageUrl($product) }}"
                                    alt="{{ $product->name }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('hidden');"
                                >
                                <div class="hidden h-full items-center justify-center text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                    {{ __('No image') }}
                                </div>
                            @else
                                <div class="flex h-full items-center justify-center text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                    {{ __('No image') }}
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2 p-4">
                            <h3 class="line-clamp-2 text-sm font-semibold leading-5 text-zinc-900 dark:text-zinc-100">{{ $product->name }}</h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $product->category?->name ?? __('Uncategorized') }}
                                @if ($product->subcategory)
                                    <span>• {{ $product->subcategory->name }}</span>
                                @endif
                            </p>

                            <div class="flex items-center justify-between pt-1">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->formatMinorAmount($this->productCardPrice($product)) }}
                                </p>

                                @if ($this->hasStock($product))
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('In stock') }}</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Out of stock') }}</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <a
                                    href="{{ $productDetailUrl }}"
                                    wire:navigate
                                    x-on:click.stop
                                    class="inline-flex items-center gap-1 rounded-sm border border-zinc-300 px-2.5 py-1.5 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200"
                                >
                                    <flux:icon.eye class="size-3.5" />
                                    <span>{{ __('Ver') }}</span>
                                </a>
                                <button
                                    type="button"
                                    wire:click.stop="addToCart({{ $product->id }})"
                                    x-on:click.stop
                                    class="inline-flex items-center gap-1 rounded-sm border border-zinc-300 px-2.5 py-1.5 text-xs font-medium text-zinc-700 transition disabled:cursor-not-allowed disabled:border-zinc-200 disabled:bg-zinc-100 disabled:text-zinc-400 dark:border-zinc-700 dark:text-zinc-200 dark:disabled:border-zinc-700 dark:disabled:bg-zinc-800 dark:disabled:text-zinc-500"
                                    @disabled(! $this->hasStock($product))
                                >
                                    <flux:icon.shopping-bag class="size-3.5" />
                                    <span>{{ __('Agregar') }}</span>
                                </button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-sm border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('No products found for the selected filters.') }}
                    </div>
                @endforelse
            </div>

            <div>
                {{ $this->products->links() }}
            </div>
        </div>
    </div>
</section>
