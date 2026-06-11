<?php

use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// Public landing page
Route::inertia('/', 'welcome')->name('home');

// Public online store
Route::get('/store', [StoreController::class, 'index'])->name('store.index');

// Authenticated dashboard
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
