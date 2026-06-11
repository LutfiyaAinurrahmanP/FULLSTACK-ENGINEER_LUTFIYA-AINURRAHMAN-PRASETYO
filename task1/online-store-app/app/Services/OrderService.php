<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * OrderService — core business logic for creating orders.
 *
 * Race Condition Handling:
 * ─────────────────────────────────────────────────────────────────────────
 * During a flash sale, many concurrent requests will try to decrement the
 * same inventory row simultaneously. Without synchronization, two requests
 * could both read stock = 1, both believe stock is sufficient, both
 * decrement, and the final quantity would be -1 (negative inventory).
 *
 * We solve this with a **pessimistic lock** (`lockForUpdate()`).
 * Inside a database transaction, `SELECT … FOR UPDATE` acquires an
 * exclusive row-level lock on the inventory row. Subsequent requests
 * that arrive before the first transaction commits will **block** and
 * wait, then re-read the (now-decremented) quantity. This guarantees
 * serialized access to the critical section without application-level
 * queuing or Redis.
 *
 * Additionally, the inventory.quantity column is UNSIGNED at the DB level,
 * providing a final safety net so that negative values can never be stored
 * even if a race slips through.
 * ─────────────────────────────────────────────────────────────────────────
 */
class OrderService
{
    /**
     * Create a new order with one or more items.
     *
     * Each item is expected to have the shape:
     *   [ 'product_id' => int, 'quantity' => int ]
     *
     * @param  array{customer_name: string, customer_email: string, items: array, notes?: string}  $data
     * @throws ValidationException when a product is unavailable or out of stock
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // 1. Create the order header (pending until items are attached)
            $order = Order::create([
                'customer_name'  => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'notes'          => $data['notes'] ?? null,
                'status'         => 'pending',
                'total_amount'   => 0,
            ]);

            $total = 0;

            // 2. Process each order line
            foreach ($data['items'] as $itemData) {
                $productId = $itemData['product_id'];
                $quantity  = (int) $itemData['quantity'];

                // 2a. Fetch product (will 404 via findOrFail if not found)
                $product = Product::findOrFail($productId);

                // 2b. Acquire a pessimistic exclusive lock on the inventory row.
                //     `lockForUpdate()` issues SELECT … FOR UPDATE which blocks
                //     other transactions from reading or modifying this row until
                //     the current transaction is committed or rolled back.
                //     This is the key to preventing race conditions during a flash sale.
                /** @var Inventory|null $inventory */
                $inventory = Inventory::where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        'items' => "Product [{$product->name}] has no inventory record.",
                    ]);
                }

                // 2c. Check sufficient stock AFTER acquiring the lock
                if (! $inventory->hasSufficientStock($quantity)) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for product [{$product->name}]. "
                            . "Requested: {$quantity}, Available: {$inventory->quantity}.",
                    ]);
                }

                // 2d. Determine effective unit price (flash sale or normal)
                $isFlashSale = $product->isFlashSaleActive();
                $unitPrice   = $product->getEffectivePrice();
                $subtotal    = $unitPrice * $quantity;

                // 2e. Decrement inventory — safe because we hold the lock
                $inventory->decrement('quantity', $quantity);

                // 2f. Create the order item with a price snapshot
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $productId,
                    'quantity'           => $quantity,
                    'unit_price'         => $unitPrice,
                    'is_flash_sale_price' => $isFlashSale,
                    'subtotal'           => $subtotal,
                ]);

                $total += $subtotal;
            }

            // 3. Update the order total and mark as confirmed
            $order->update([
                'total_amount' => $total,
                'status'       => 'confirmed',
            ]);

            // 4. Eager-load items + products for the response
            return $order->load('items.product');
        });
    }

    /**
     * Cancel an order and restore inventory quantities.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function cancelOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Order is already cancelled.',
                ]);
            }

            // Restore inventory for each item
            foreach ($order->items as $item) {
                Inventory::where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('quantity', $item->quantity);
            }

            $order->update(['status' => 'cancelled']);

            return $order->fresh('items.product');
        });
    }
}
