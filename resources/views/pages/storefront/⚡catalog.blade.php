<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
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
    #[Url(as: 'colors')]
    public array $colors = [];

    /** @var list<string> */
    #[Url(as: 'sizes')]
    public array $sizes = [];

    /** @var list<string> */
    #[Url(as: 'cats')]
    public array $categories = [];

    /** @var list<string> */
    #[Url(as: 'subs')]
    public array $subcategories = [];

    #[Url(as: 'min')]
    public string $priceMin = '';

    #[Url(as: 'max')]
    public string $priceMax = '';

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

    public function updatedColors(): void
    {
        $this->resetPage();
    }

    public function updatedSizes(): void
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

    #[Computed]
    public function availableColors()
    {
        return ProductVariant::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('color')
            ->whereHas('product', fn ($query) => $query->whereNull('deleted_at'))
            ->distinct()
            ->orderBy('color')
            ->pluck('color')
            ->filter(fn (?string $color): bool => $color !== null && trim($color) !== '')
            ->values();
    }

    #[Computed]
    public function availableSizes()
    {
        return ProductVariant::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('size')
            ->whereHas('product', fn ($query) => $query->whereNull('deleted_at'))
            ->distinct()
            ->orderBy('size')
            ->pluck('size')
            ->filter(fn (?string $size): bool => $size !== null && trim($size) !== '')
            ->values();
    }

    #[Computed]
    public function products()
    {
        $searchTerm = trim($this->search);
        $selectedColors = collect($this->colors)
            ->map(fn (string $color): string => trim($color))
            ->filter()
            ->values()
            ->all();

        $selectedSizes = collect($this->sizes)
            ->map(fn (string $size): string => trim($size))
            ->filter()
            ->values()
            ->all();

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

        $minPrice = $this->normalizeMajorPriceToMinorAmount($this->priceMin);
        $maxPrice = $this->normalizeMajorPriceToMinorAmount($this->priceMax);

        return Product::query()
            ->with([
                'brand:id,name',
                'category:id,name',
                'subcategory:id,name,parent_id',
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'url', 'is_primary', 'sort_order'])
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order'),
                'variants' => fn ($query) => $query
                    ->select(['id', 'product_id', 'price', 'sale_price', 'stock_available', 'is_active'])
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ])
            ->whereNull('deleted_at')
            ->when($searchTerm !== '', fn (Builder $query) => $query->where('name', 'like', "%{$searchTerm}%"))
            ->when($selectedCategories !== [], fn (Builder $query) => $query->whereIn('category_id', $selectedCategories))
            ->when($selectedSubcategories !== [], fn (Builder $query) => $query->whereIn('subcategory_id', $selectedSubcategories))
            ->whereHas('variants', function (Builder $query) use ($selectedColors, $selectedSizes, $minPrice, $maxPrice): void {
                $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->when($selectedColors !== [], fn (Builder $variantQuery) => $variantQuery->whereIn('color', $selectedColors))
                    ->when($selectedSizes !== [], fn (Builder $variantQuery) => $variantQuery->whereIn('size', $selectedSizes))
                    ->when($minPrice !== null, fn (Builder $variantQuery) => $variantQuery->whereRaw('COALESCE(sale_price, price) >= ?', [$minPrice]))
                    ->when($maxPrice !== null, fn (Builder $variantQuery) => $variantQuery->whereRaw('COALESCE(sale_price, price) <= ?', [$maxPrice]));
            })
            ->latest('updated_at')
            ->paginate(12);
    }

    public function productCardPrice(Product $product): ?int
    {
        return $product->variants
            ->map(fn (ProductVariant $variant): ?int => $variant->sale_price ?? $variant->price)
            ->filter(fn (?int $amount): bool => $amount !== null)
            ->min();
    }

    public function hasStock(Product $product): bool
    {
        return $product->variants->contains(fn (ProductVariant $variant): bool => (int) $variant->stock_available > 0);
    }

    public function formatMinorAmount(?int $amount): string
    {
        if ($amount === null) {
            return __('Price unavailable');
        }

        return money($amount, config('services.main_store.currency', 'PEN'))->format();
    }

    protected function normalizeMajorPriceToMinorAmount(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $trimmed);
        if (! is_numeric($normalized)) {
            return null;
        }

        return max(0, (int) round(((float) $normalized) * 100));
    }
}; ?>

<section class="space-y-6">
    <header class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Catalog') }}</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Browse synced products from the main store.') }}</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Search') }}</h2>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search product name...') }}"
                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                >
            </div>

            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Price') }}</h2>
                <div class="grid grid-cols-2 gap-2">
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="priceMin"
                        placeholder="{{ __('Min') }}"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="priceMax"
                        placeholder="{{ __('Max') }}"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                </div>
            </div>

            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Colors') }}</h2>
                <div class="max-h-52 space-y-2 overflow-auto pr-1">
                    @forelse ($this->availableColors as $color)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="colors" value="{{ $color }}" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800">
                            <span>{{ $color }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-zinc-500">{{ __('No colors available') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Sizes') }}</h2>
                <div class="max-h-52 space-y-2 overflow-auto pr-1">
                    @forelse ($this->availableSizes as $size)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="sizes" value="{{ $size }}" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800">
                            <span>{{ $size }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-zinc-500">{{ __('No sizes available') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Categories') }}</h2>
                <div class="max-h-72 space-y-3 overflow-auto pr-1">
                    @forelse ($this->categoryTree as $category)
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" wire:model.live="categories" value="{{ $category->id }}" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800">
                                <span>{{ $category->name }}</span>
                            </label>

                            @if ($category->children->isNotEmpty())
                                <div class="ml-5 space-y-2">
                                    @foreach ($category->children as $subcategory)
                                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                            <input type="checkbox" wire:model.live="subcategories" value="{{ $subcategory->id }}" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800">
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
                    <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                            @if ($product->images->first()?->url)
                                <img
                                    src="{{ $product->images->first()?->url }}"
                                    alt="{{ $product->name }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                >
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
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
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
