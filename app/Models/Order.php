<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'main_store_external_order_id',
        'external_customer_id',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'placed_at',
        'cancellation_note',
        'is_refunded',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'external_customer_id' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'datetime',
            'is_refunded' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
