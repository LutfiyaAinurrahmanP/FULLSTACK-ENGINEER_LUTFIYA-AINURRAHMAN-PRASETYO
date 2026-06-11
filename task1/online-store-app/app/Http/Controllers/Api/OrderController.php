<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * OrderController — handles order creation and retrieval.
 * Delegates business logic (including race-condition-safe inventory
 * decrement) to OrderService.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * GET /api/orders
     * Returns a paginated list of orders with their items.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('items.product')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('email'), fn ($q) => $q->where('customer_email', $request->email))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data'    => $orders->through(fn ($o) => $this->formatOrder($o)),
        ]);
    }

    /**
     * GET /api/orders/{order}
     * Returns a single order with full item details.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load('items.product');

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully.',
            'data'    => $this->formatOrder($order),
        ]);
    }

    /**
     * POST /api/orders
     * Creates a new order.
     *
     * Business rules enforced:
     *   - At least one item required.
     *   - Each item must reference a valid product with sufficient stock.
     *   - Inventory is decremented atomically using pessimistic locking
     *     (see OrderService) to prevent race conditions during flash sales.
     *
     * Example request body:
     * {
     *   "customer_name": "Jane Doe",
     *   "customer_email": "jane@example.com",
     *   "items": [
     *     { "product_id": 1, "quantity": 2 }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name'          => 'required|string|max:255',
            'customer_email'         => 'required|email|max:255',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|integer|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
        ]);

        try {
            $order = $this->orderService->createOrder($validated);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'data'    => $this->formatOrder($order),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order could not be placed.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * PATCH /api/orders/{order}/cancel
     * Cancels a confirmed order and restores inventory.
     */
    public function cancel(Order $order): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder($order);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data'    => $this->formatOrder($order),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** Formats an order with its items for the API response. */
    private function formatOrder(Order $order): array
    {
        return [
            'id'             => $order->id,
            'customer_name'  => $order->customer_name,
            'customer_email' => $order->customer_email,
            'status'         => $order->status,
            'total_amount'   => (float) $order->total_amount,
            'notes'          => $order->notes,
            'items'          => $order->items->map(fn ($item) => [
                'id'                 => $item->id,
                'product_id'         => $item->product_id,
                'product_name'       => $item->product?->name,
                'quantity'           => $item->quantity,
                'unit_price'         => (float) $item->unit_price,
                'is_flash_sale_price' => $item->is_flash_sale_price,
                'subtotal'           => (float) $item->subtotal,
            ]),
            'created_at' => $order->created_at->toIso8601String(),
            'updated_at' => $order->updated_at->toIso8601String(),
        ];
    }
}
