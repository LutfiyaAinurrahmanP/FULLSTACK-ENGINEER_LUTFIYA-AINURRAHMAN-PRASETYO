<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Online Store
|--------------------------------------------------------------------------
| All routes return JSON. Grouped under /api prefix (set in bootstrap/app.php).
|
| Products
|   GET    /api/products              – List all products (supports ?flash_sale=1)
|   GET    /api/products/{product}    – Show a single product
|   POST   /api/products              – Create a product (admin)
|   PUT    /api/products/{product}    – Update a product (admin)
|   DELETE /api/products/{product}    – Soft-delete a product (admin)
|   PUT    /api/products/{product}/stock – Restock a product (admin)
|
| Orders
|   GET    /api/orders                – List all orders
|   GET    /api/orders/{order}        – Show a single order
|   POST   /api/orders                – Create a new order (handles flash sale race condition)
|   PATCH  /api/orders/{order}/cancel – Cancel an order & restore inventory
*/

// ------------------------------------------------------------------
// Product endpoints
// ------------------------------------------------------------------
Route::apiResource('products', ProductController::class);
Route::put('products/{product}/stock', [ProductController::class, 'updateStock'])
    ->name('products.stock');

// ------------------------------------------------------------------
// Order endpoints
// ------------------------------------------------------------------
Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])
    ->name('orders.cancel');

// ------------------------------------------------------------------
// Health check
// ------------------------------------------------------------------
Route::get('health', fn () => response()->json([
    'status'    => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('health');
