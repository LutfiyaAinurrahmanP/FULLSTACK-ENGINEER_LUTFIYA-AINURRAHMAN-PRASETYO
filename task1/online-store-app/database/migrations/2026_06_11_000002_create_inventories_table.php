<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the inventories table — tracks stock per product.
     * Uses unsignedInteger to prevent negative quantity at DB level.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            // UNSIGNED ensures quantity can never go below 0 at the database level
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique('product_id'); // One inventory record per product
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
