<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;

class ProductImageService
{
    public function primaryImageUrl(?Product $product): ?string
    {
        if ($product === null) {
            return null;
        }

        $images = $this->sortedProductImages($product);
        $primaryUrl = trim((string) ($images->first()?->url ?? ''));

        if ($primaryUrl !== '') {
            return $primaryUrl;
        }

        $featuredImage = trim((string) ($product->featured_image ?? ''));
        if ($featuredImage !== '') {
            return $featuredImage;
        }

        return null;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    protected function sortedProductImages(Product $product): Collection
    {
        if ($product->relationLoaded('images')) {
            return $product->images
                ->sortBy([
                    ['is_primary', 'desc'],
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        return ProductImage::query()
            ->where('product_id', $product->id)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'product_id', 'url', 'is_primary', 'sort_order']);
    }
}
