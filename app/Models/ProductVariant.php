<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'external_ref',
        'product_id',
        'sku',
        'code',
        'price',
        'sale_price',
        'color',
        'hex',
        'size',
        'stock_on_hand',
        'stock_reserved',
        'stock_available',
        'is_active',
        'primary_image_url',
        'remote_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'product_id' => 'integer',
            'price' => 'integer',
            'sale_price' => 'integer',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'stock_available' => 'integer',
            'is_active' => 'boolean',
            'remote_updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function formattedPrice(?string $currency = null): string
    {
        return $this->formatMonetaryAmount($this->price, $currency);
    }

    public function formattedSalePrice(?string $currency = null): string
    {
        return $this->formatMonetaryAmount($this->sale_price, $currency);
    }

    protected function formatMonetaryAmount(?int $amount, ?string $currency = null): string
    {
        if ($amount === null) {
            return '-';
        }

        $resolvedCurrency = $currency ?? config('services.main_store.currency', 'PEN');

        return money($amount, $resolvedCurrency)->format();
    }
}
