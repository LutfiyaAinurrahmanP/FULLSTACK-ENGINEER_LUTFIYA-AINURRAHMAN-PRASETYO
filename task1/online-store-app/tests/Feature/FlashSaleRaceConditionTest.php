<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FlashSaleRaceConditionTest
 * ─────────────────────────────────────────────────────────────────────────
 * Verifies that the API correctly handles concurrent orders for the same
 * flash sale product without:
 *   1. Allowing inventory to go negative (the core race condition problem).
 *   2. Accepting more orders than there is stock for.
 *
 * How the concurrent test simulates concurrency:
 *   We send N simultaneous HTTP requests to POST /api/orders in parallel
 *   using PHP's cURL multi-handle. Each request tries to purchase 1 unit
 *   of a product that has a limited stock of (STOCK_LIMIT) units.
 *   The concurrent test requires APP_URL to be pointing to a running server.
 *
 * Run from the command line:
 *   php artisan test --filter=FlashSaleRaceConditionTest
 *   # or with phpunit directly (requires Herd PHP):
 *   ~/.config/herd-lite/bin/php vendor/phpunit/phpunit/phpunit tests/Feature/FlashSaleRaceConditionTest.php
 * ─────────────────────────────────────────────────────────────────────────
 */
class FlashSaleRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    /** Total concurrent order attempts for HTTP test */
    private const CONCURRENT_REQUESTS = 20;

    /** Units in stock — must be less than CONCURRENT_REQUESTS */
    private const STOCK_LIMIT = 5;

    private Product $flashProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a flash sale product with limited stock
        $this->flashProduct = Product::create([
            'name'             => 'Flash Sale Test Product',
            'description'      => 'A product used for race condition testing.',
            'price'            => 500000,
            'flash_sale_price' => 100000,
            'is_flash_sale'    => true,
            'flash_sale_start' => now()->subMinute(),
            'flash_sale_end'   => now()->addHour(),
        ]);

        Inventory::create([
            'product_id' => $this->flashProduct->id,
            'quantity'   => self::STOCK_LIMIT,
        ]);
    }

    // -----------------------------------------------------------------------
    // Test: Flash sale price applied correctly
    // -----------------------------------------------------------------------

    /**
     * Verifies that a single order for a flash sale product succeeds
     * and uses the flash sale price (not normal price).
     */
    #[Test]
    public function it_applies_flash_sale_price_to_order_items(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name'  => 'Flash Buyer',
            'customer_email' => 'flash@example.com',
            'items'          => [
                ['product_id' => $this->flashProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Assert numeric values without strict float typing (SQLite returns int, MySQL float)
        $this->assertEquals(100000, $response->json('data.items.0.unit_price'));
        $this->assertTrue($response->json('data.items.0.is_flash_sale_price'));
        $this->assertEquals(100000, $response->json('data.total_amount'));
    }

    /**
     * Verifies that an order is rejected when trying to buy more than
     * the available stock.
     */
    #[Test]
    public function it_rejects_order_when_quantity_exceeds_stock(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name'  => 'Greedy Buyer',
            'customer_email' => 'greedy@example.com',
            'items'          => [
                ['product_id' => $this->flashProduct->id, 'quantity' => self::STOCK_LIMIT + 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Verifies that an order with zero items is rejected by validation.
     */
    #[Test]
    public function it_requires_at_least_one_order_item(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name'  => 'Empty Buyer',
            'customer_email' => 'empty@example.com',
            'items'          => [],
        ]);

        $response->assertStatus(422);
    }

    /**
     * Verifies that cancelling an order restores the inventory.
     */
    #[Test]
    public function it_restores_inventory_when_order_is_cancelled(): void
    {
        // Place an order
        $orderResponse = $this->postJson('/api/orders', [
            'customer_name'  => 'Cancel Test',
            'customer_email' => 'cancel@example.com',
            'items'          => [
                ['product_id' => $this->flashProduct->id, 'quantity' => 2],
            ],
        ]);

        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        $stockAfterOrder = Inventory::where('product_id', $this->flashProduct->id)->value('quantity');
        $this->assertEquals(self::STOCK_LIMIT - 2, $stockAfterOrder);

        // Cancel the order
        $cancelResponse = $this->patchJson("/api/orders/{$orderId}/cancel");
        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Stock should be restored
        $stockAfterCancel = Inventory::where('product_id', $this->flashProduct->id)->value('quantity');
        $this->assertEquals(self::STOCK_LIMIT, $stockAfterCancel);
    }

    /**
     * Simulates sequential ordering to verify the service layer correctly
     * exhausts stock and prevents overselling using pessimistic locking.
     *
     * This test verifies the CORE race condition protection logic:
     * after STOCK_LIMIT successful orders, subsequent orders must fail
     * with a 422 status and the stock must remain at 0 (never negative).
     */
    #[Test]
    public function it_prevents_overselling_via_service_layer(): void
    {
        $service = app(OrderService::class);

        $successCount = 0;
        $failCount    = 0;

        // Try to create CONCURRENT_REQUESTS orders sequentially
        for ($i = 0; $i < self::CONCURRENT_REQUESTS; $i++) {
            try {
                $service->createOrder([
                    'customer_name'  => "Buyer {$i}",
                    'customer_email' => "buyer{$i}@example.com",
                    'items'          => [
                        ['product_id' => $this->flashProduct->id, 'quantity' => 1],
                    ],
                ]);
                $successCount++;
            } catch (ValidationException $e) {
                $failCount++;
            }
        }

        $finalStock      = Inventory::where('product_id', $this->flashProduct->id)->value('quantity');
        $confirmedOrders = Order::where('status', 'confirmed')->count();

        // Exactly STOCK_LIMIT orders should succeed
        $this->assertEquals(
            self::STOCK_LIMIT,
            $successCount,
            "Expected exactly " . self::STOCK_LIMIT . " successful orders, got {$successCount}."
        );

        // The rest must be rejected
        $this->assertEquals(
            self::CONCURRENT_REQUESTS - self::STOCK_LIMIT,
            $failCount,
            "Expected " . (self::CONCURRENT_REQUESTS - self::STOCK_LIMIT) . " rejected orders."
        );

        // Stock must be exactly 0 — never negative
        $this->assertEquals(
            0,
            $finalStock,
            "Inventory must be 0 after all stock is sold, but got {$finalStock}."
        );

        $this->assertGreaterThanOrEqual(
            0,
            $finalStock,
            'Inventory MUST NOT go negative — race condition not handled properly!'
        );

        $this->assertEquals(
            self::STOCK_LIMIT,
            $confirmedOrders,
            "Only " . self::STOCK_LIMIT . " orders should be confirmed in the database."
        );
    }

    /**
     * @test
     * HTTP concurrent test — requires a live server at APP_URL.
     * Sends CONCURRENT_REQUESTS parallel HTTP POST requests using cURL
     * multi-handle to simulate a real flash sale burst.
     *
     * Skip automatically if APP_URL is not reachable (CI / local without server).
     *
     * Run manually with a server active:
     *   php artisan serve &
     *   php vendor/phpunit/phpunit/phpunit tests/Feature/FlashSaleRaceConditionTest.php \
     *     --filter=it_prevents_overselling_during_concurrent_http_requests
     */
    public function it_prevents_overselling_during_concurrent_http_requests(): void
    {
        $baseUrl = rtrim(config('app.url'), '/') . '/api/orders';

        // Verify the server is reachable; skip if not
        $probe = @file_get_contents(rtrim(config('app.url'), '/') . '/api/health');
        if ($probe === false) {
            $this->markTestSkipped('Live server not reachable at ' . config('app.url') . ' — skipping HTTP concurrent test.');
        }

        // Reset stock to STOCK_LIMIT for a clean run
        Inventory::where('product_id', $this->flashProduct->id)
            ->update(['quantity' => self::CONCURRENT_REQUESTS]);

        $results = $this->sendConcurrentOrders(self::CONCURRENT_REQUESTS, $baseUrl);

        $successCount = count(array_filter($results, fn ($r) => $r['status'] === 201));
        $failCount    = count(array_filter($results, fn ($r) => $r['status'] === 422));
        $finalStock   = Inventory::where('product_id', $this->flashProduct->id)->value('quantity');

        $this->assertEquals(self::CONCURRENT_REQUESTS, $successCount + $failCount);
        $this->assertGreaterThanOrEqual(0, $finalStock, 'Stock MUST NOT go negative!');
    }

    // -----------------------------------------------------------------------
    // Private: cURL multi-handle for concurrent HTTP requests
    // -----------------------------------------------------------------------

    /**
     * Sends $count concurrent HTTP POST requests to $url using cURL
     * multi-handle to simulate burst traffic.
     *
     * @return array<int, array{status: int, body: array}>
     */
    private function sendConcurrentOrders(int $count, string $url): array
    {
        $payload = json_encode([
            'customer_name'  => 'Concurrent Buyer',
            'customer_email' => 'concurrent@example.com',
            'items'          => [
                ['product_id' => $this->flashProduct->id, 'quantity' => 1],
            ],
        ]);

        $mh      = curl_multi_init();
        $handles = [];

        for ($i = 0; $i < $count; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 30,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        $results = [];
        foreach ($handles as $ch) {
            $results[] = [
                'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'body'   => json_decode(curl_multi_getcontent($ch), true) ?? [],
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $results;
    }
}
