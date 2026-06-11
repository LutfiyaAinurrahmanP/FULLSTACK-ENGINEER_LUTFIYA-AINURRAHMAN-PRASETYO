<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductController — RESTful API for product management.
 * Provides listing, showing, and admin-level create/update/delete.
 */
class ProductController extends Controller
{
    /**
     * GET /api/products
     * Returns a paginated list of all active (non-deleted) products
     * with their current inventory and flash sale status.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('inventory')
            ->when($request->boolean('flash_sale'), fn ($q) => $q->where('is_flash_sale', true))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data'    => $products->through(fn ($p) => $this->formatProduct($p)),
        ]);
    }

    /**
     * GET /api/products/{product}
     * Returns a single product with inventory and flash sale details.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load('inventory');

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data'    => $this->formatProduct($product),
        ]);
    }

    /**
     * POST /api/products
     * Creates a new product. In a production app this would be
     * restricted to admin users via middleware.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'price'             => 'required|numeric|min:0',
            'flash_sale_price'  => 'nullable|numeric|min:0|lt:price',
            'is_flash_sale'     => 'boolean',
            'flash_sale_start'  => 'nullable|date',
            'flash_sale_end'    => 'nullable|date|after:flash_sale_start',
            'image_url'         => 'nullable|url',
            'initial_stock'     => 'required|integer|min:0',
        ]);

        $product = Product::create($validated);

        // Auto-create inventory record with initial stock
        $product->inventory()->create(['quantity' => $validated['initial_stock']]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => $this->formatProduct($product->load('inventory')),
        ], 201);
    }

    /**
     * PUT /api/products/{product}
     * Updates an existing product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'sometimes|numeric|min:0',
            'flash_sale_price' => 'nullable|numeric|min:0',
            'is_flash_sale'    => 'boolean',
            'flash_sale_start' => 'nullable|date',
            'flash_sale_end'   => 'nullable|date',
            'image_url'        => 'nullable|url',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data'    => $this->formatProduct($product->fresh('inventory')),
        ]);
    }

    /**
     * DELETE /api/products/{product}
     * Soft-deletes a product (preserves order history).
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * PUT /api/products/{product}/stock
     * Adjusts the inventory quantity for a product (restock / correction).
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $inventory = $product->inventory;

        if ($inventory) {
            $inventory->update(['quantity' => $validated['quantity']]);
        } else {
            $product->inventory()->create(['quantity' => $validated['quantity']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully.',
            'data'    => [
                'product_id' => $product->id,
                'quantity'   => $validated['quantity'],
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** Formats a product for the API response. */
    private function formatProduct(Product $product): array
    {
        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'description'      => $product->description,
            'price'            => (float) $product->price,
            'effective_price'  => $product->getEffectivePrice(),
            'flash_sale'       => [
                'active'      => $product->isFlashSaleActive(),
                'price'       => $product->flash_sale_price ? (float) $product->flash_sale_price : null,
                'starts_at'   => $product->flash_sale_start?->toIso8601String(),
                'ends_at'     => $product->flash_sale_end?->toIso8601String(),
            ],
            'image_url'        => $product->image_url,
            'stock'            => $product->inventory?->quantity ?? 0,
            'created_at'       => $product->created_at->toIso8601String(),
            'updated_at'       => $product->updated_at->toIso8601String(),
        ];
    }
}
