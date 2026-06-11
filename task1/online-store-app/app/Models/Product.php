<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product model representing an item sold in the online store.
 * Supports flash sale mode with a discounted price and time window.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'flash_sale_price',
        'is_flash_sale',
        'flash_sale_start',
        'flash_sale_end',
        'image_url',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'flash_sale_price' => 'decimal:2',
        'is_flash_sale'    => 'boolean',
        'flash_sale_start' => 'datetime',
        'flash_sale_end'   => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** One-to-one: each product has a single inventory record. */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /** One-to-many: a product can appear in many order items. */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // -----------------------------------------------------------------------
    // Accessors / Helpers
    // -----------------------------------------------------------------------

    /**
     * Returns the effective selling price.
     * If a flash sale is currently active, returns the discounted price.
     */
    public function getEffectivePrice(): float
    {
        if ($this->isFlashSaleActive()) {
            return (float) $this->flash_sale_price;
        }

        return (float) $this->price;
    }

    /**
     * Checks whether a flash sale is currently active for this product.
     * Validates: flag enabled, price set, and within the time window.
     */
    public function isFlashSaleActive(): bool
    {
        if (! $this->is_flash_sale || ! $this->flash_sale_price) {
            return false;
        }

        $now = now();

        if ($this->flash_sale_start && $now->lt($this->flash_sale_start)) {
            return false;
        }

        if ($this->flash_sale_end && $now->gt($this->flash_sale_end)) {
            return false;
        }

        return true;
    }

    /**
     * Returns current stock quantity (0 if no inventory record).
     */
    public function getStockQuantity(): int
    {
        return $this->inventory?->quantity ?? 0;
    }
}
