<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order model — represents a customer's purchase transaction.
 * An order must contain at least one OrderItem (enforced in OrderService).
 */
class Order extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_email',
        'status',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** An order has one or more line items. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Recalculates and saves the total_amount from all order items.
     */
    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('subtotal');
        $this->save();
    }
}
