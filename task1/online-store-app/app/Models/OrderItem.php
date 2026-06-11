<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem model — a single product line within an order.
 * Stores a price snapshot at purchase time so historical orders
 * are not affected by future price changes.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'is_flash_sale_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity'           => 'integer',
        'unit_price'         => 'decimal:2',
        'is_flash_sale_price' => 'boolean',
        'subtotal'           => 'decimal:2',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** Each item belongs to one order. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Each item references one product (price snapshot is stored separately). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
