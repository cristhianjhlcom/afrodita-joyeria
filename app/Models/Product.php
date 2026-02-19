<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_OUT_OF_STOCK = 'out_of_stock';

    public const STATUS_PRE_ORDER = 'pre_order';

    public const STATUS_BACKORDERED = 'backordered';

    public const STATUS_DISCONTINUED = 'discontinued';

    public const STATUS_SOLD_OUT = 'sold_out';

    public const STATUS_COMING_SOON = 'coming_soon';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'brand_id',
        'subcategory_id',
        'name',
        'slug',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'brand_id' => 'integer',
            'subcategory_id' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
