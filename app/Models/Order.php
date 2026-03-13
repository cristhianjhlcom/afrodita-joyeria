<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_token',
        'source',
        'user_id',
        'external_id',
        'main_store_external_order_id',
        'main_store_order_id',
        'external_customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_document_type',
        'customer_document_number',
        'shipping_country_id',
        'shipping_department_id',
        'shipping_province_id',
        'shipping_district_id',
        'shipping_address_line',
        'shipping_reference',
        'shipping_method',
        'payment_gateway',
        'payment_status',
        'payment_reference',
        'payment_error_code',
        'payment_error_message',
        'paid_at',
        'push_status',
        'push_attempts',
        'push_last_error',
        'push_last_response',
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
            'user_id' => 'integer',
            'external_id' => 'integer',
            'main_store_order_id' => 'integer',
            'external_customer_id' => 'integer',
            'shipping_country_id' => 'integer',
            'shipping_department_id' => 'integer',
            'shipping_province_id' => 'integer',
            'shipping_district_id' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'push_attempts' => 'integer',
            'push_last_response' => 'array',
            'is_refunded' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
