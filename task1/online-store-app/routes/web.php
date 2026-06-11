<?php

use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

use App\Models\Product;
use App\Models\Order;
use Inertia\Inertia;

// Public landing page
Route::get('/', function () {
    $productsCount = Product::count();
    $customersCount = Order::distinct('customer_email')->count('customer_email');
    
    $now = now();
    $flashSalesCount = Product::where('is_flash_sale', true)
        ->where(function ($query) use ($now) {
            $query->whereNull('flash_sale_start')->orWhere('flash_sale_start', '<=', $now);
        })
        ->where(function ($query) use ($now) {
            $query->whereNull('flash_sale_end')->orWhere('flash_sale_end', '>=', $now);
        })
        ->count();

    return Inertia::render('welcome', [
        'stats' => [
            'products' => $productsCount,
            'customers' => $customersCount,
            'flash_sales' => $flashSalesCount,
        ]
    ]);
})->name('home');

// Public online store
Route::get('/store', [StoreController::class, 'index'])->name('store.index');

// Authenticated dashboard
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
