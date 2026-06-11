<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the order_items table. Each row represents one product line
     * within an order. Price is captured at purchase time (snapshot).
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');
            $table->foreignId('product_id')
                ->constrained('products');
            $table->unsignedInteger('quantity');
            // Snapshot of the price at the time of purchase
            $table->decimal('unit_price', 12, 2);
            // Whether the item was purchased during a flash sale
            $table->boolean('is_flash_sale_price')->default(false);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
