<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * ProductSeeder — populates the database with sample products
 * including a flash sale product for testing race condition handling.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // ----------------------------------------------------------------
            // Regular products
            // ----------------------------------------------------------------
            [
                'name'        => 'Mechanical Keyboard Pro X',
                'description' => 'High-performance mechanical keyboard with RGB lighting, tactile switches, and aluminum frame. Perfect for gaming and coding.',
                'price'       => 1299000,
                'stock'       => 50,
                'image_url'   => null,
            ],
            [
                'name'        => 'Wireless Noise-Cancelling Headphones',
                'description' => 'Premium over-ear headphones with 30-hour battery life, active noise cancellation, and Hi-Res audio support.',
                'price'       => 2499000,
                'stock'       => 30,
                'image_url'   => null,
            ],
            [
                'name'        => 'Ergonomic Office Chair',
                'description' => 'Fully adjustable lumbar support, mesh back, and 4D armrests. Designed for all-day comfort.',
                'price'       => 3750000,
                'stock'       => 20,
                'image_url'   => null,
            ],
            [
                'name'        => 'USB-C Hub 10-in-1',
                'description' => '10-port hub: 4K HDMI, 3x USB-A, 2x USB-C, SD/microSD, Ethernet, and 100W PD charging.',
                'price'       => 450000,
                'stock'       => 100,
                'image_url'   => null,
            ],
            [
                'name'        => 'Smart LED Desk Lamp',
                'description' => 'Touch-controlled desk lamp with 5 color temperatures, brightness adjustment, and built-in wireless charger.',
                'price'       => 350000,
                'stock'       => 75,
                'image_url'   => null,
            ],

            // ----------------------------------------------------------------
            // Flash Sale product — limited stock to demonstrate race condition
            // handling. Only 10 units available but many concurrent buyers expected.
            // ----------------------------------------------------------------
            [
                'name'             => '⚡ FLASH SALE — Gaming Mouse Pro',
                'description'      => 'Ultra-lightweight gaming mouse, 25K DPI optical sensor, 6 programmable buttons. LIMITED STOCK — only 10 units at flash sale price!',
                'price'            => 899000,
                'flash_sale_price' => 299000,   // ~67% discount
                'is_flash_sale'    => true,
                'flash_sale_start' => now()->subMinute(),  // Already started
                'flash_sale_end'   => now()->addHours(2),  // Ends in 2 hours
                'stock'            => 10,                  // Deliberately low to show race condition prevention
                'image_url'        => null,
            ],

            // Second flash sale product
            [
                'name'             => '⚡ FLASH SALE — 27" 165Hz Monitor',
                'description'      => '27" IPS gaming monitor, 165Hz refresh rate, 1ms response, HDR400. FLASH SALE — grab it before it\'s gone!',
                'price'            => 5500000,
                'flash_sale_price' => 3299000,  // ~40% discount
                'is_flash_sale'    => true,
                'flash_sale_start' => now()->subMinute(),
                'flash_sale_end'   => now()->addHour(),
                'stock'            => 5,
                'image_url'        => null,
            ],
        ];

        foreach ($products as $data) {
            $stock = $data['stock'];
            unset($data['stock']);

            $product = Product::create($data);

            Inventory::create([
                'product_id' => $product->id,
                'quantity'   => $stock,
            ]);
        }

        $this->command->info('✅ ProductSeeder: seeded ' . count($products) . ' products.');
    }
}
