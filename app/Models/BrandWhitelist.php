<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandWhitelist extends Model
{
    /** @use HasFactory<\Database\Factories\BrandWhitelistFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'enabled',
        'main_store_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'main_store_token' => 'encrypted',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
