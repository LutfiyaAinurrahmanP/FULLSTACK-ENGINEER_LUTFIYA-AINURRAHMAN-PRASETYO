<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * StoreController — serves Inertia pages for the public-facing online store.
 * All product data is passed directly as Inertia props (no separate API call needed).
 */
class StoreController extends Controller
{
    /**
     * GET /store
     * Displays the product listing page.
     * Supports filtering by flash sale and searching by name.
     */
    public function index(Request $request): Response
    {
        $products = Product::with('inventory')
            ->when($request->boolean('flash_sale'), fn ($q) => $q->where('is_flash_sale', true))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->paginate(12)
            ->through(fn ($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'description'     => $p->description,
                'price'           => (float) $p->price,
                'effective_price' => $p->getEffectivePrice(),
                'flash_sale'      => [
                    'active'    => $p->isFlashSaleActive(),
                    'price'     => $p->flash_sale_price ? (float) $p->flash_sale_price : null,
                    'starts_at' => $p->flash_sale_start?->toIso8601String(),
                    'ends_at'   => $p->flash_sale_end?->toIso8601String(),
                ],
                'image_url' => $p->image_url,
                'stock'     => $p->inventory?->quantity ?? 0,
            ]);

        return Inertia::render('store/index', [
            'products'       => $products,
            'flash_sale_only' => $request->boolean('flash_sale'),
        ]);
    }
}
