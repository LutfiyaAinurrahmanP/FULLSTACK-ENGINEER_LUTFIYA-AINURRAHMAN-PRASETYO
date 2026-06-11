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
        $faker = \Faker\Factory::create();
        $totalSeeded = 0;

        // ----------------------------------------------------------------
        // 25 Regular products
        // ----------------------------------------------------------------
        for ($i = 1; $i <= 25; $i++) {
            $stock = $faker->numberBetween(10, 100);
            $product = Product::create([
                'name'        => ucfirst($faker->words(3, true)) . ' ' . $i,
                'description' => $faker->paragraph(),
                'price'       => $faker->numberBetween(100, 5000) * 1000,
                'image_url'   => null,
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'quantity'   => $stock,
            ]);
            $totalSeeded++;
        }

        // ----------------------------------------------------------------
        // 8 Flash Sale products
        // ----------------------------------------------------------------
        for ($i = 1; $i <= 8; $i++) {
            $price = $faker->numberBetween(1000, 8000) * 1000;
            $flashPrice = $price * $faker->randomFloat(2, 0.3, 0.7);
            $stock = $faker->numberBetween(5, 20);

            $product = Product::create([
                'name'             => '⚡ FLASH SALE — ' . ucfirst($faker->words(2, true)) . ' ' . $i,
                'description'      => $faker->paragraph() . ' LIMITED STOCK!',
                'price'            => $price,
                'flash_sale_price' => $flashPrice,
                'is_flash_sale'    => true,
                'flash_sale_start' => now()->subMinutes(rand(1, 60)),
                'flash_sale_end'   => now()->addHours(rand(1, 24)),
                'image_url'        => null,
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'quantity'   => $stock,
            ]);
            $totalSeeded++;
        }

        $this->command->info('✅ ProductSeeder: seeded ' . $totalSeeded . ' products.');
    }
}
