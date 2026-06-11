<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventory model — tracks the stock quantity for a single product.
 * The quantity column is UNSIGNED at the database level, preventing
 * negative values from being persisted even outside application logic.
 */
class Inventory extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** Each inventory record belongs to one product. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Checks whether the requested quantity can be fulfilled.
     */
    public function hasSufficientStock(int $requested): bool
    {
        return $this->quantity >= $requested;
    }
}
