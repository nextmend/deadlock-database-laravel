<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Product::create(['id' => 1, 'sku' => 'KB-01', 'name' => 'Keyboard', 'stock' => 100]);
        Product::create(['id' => 2, 'sku' => 'MS-02', 'name' => 'Mouse', 'stock' => 100]);
    }

    public function test_sequential_checkout_succeeds(): void
    {
        $this->postJson('/checkout', [
            'order_number' => 'ORD-101',
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ])->assertOk();

        $this->postJson('/checkout', [
            'order_number' => 'ORD-102',
            'items' => [
                ['product_id' => 2, 'quantity' => 1],
                ['product_id' => 1, 'quantity' => 1],
            ],
        ])->assertOk();

        $this->assertSame(98, Product::find(1)->stock);
        $this->assertSame(98, Product::find(2)->stock);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        $this->postJson('/checkout', [
            'order_number' => 'ORD-103',
            'items' => [
                ['product_id' => 1, 'quantity' => 101],
            ],
        ])->assertStatus(500);

        $this->assertSame(100, Product::find(1)->stock);
    }

    public function test_checkout_locks_products_in_deterministic_order(): void
    {
        $lockedProductIds = [];

        DB::listen(function ($query) use (&$lockedProductIds) {
            $sql = strtolower($query->sql);
            if (str_starts_with($sql, 'select') && str_contains($sql, 'products')) {
                foreach ($query->bindings as $binding) {
                    if (is_numeric($binding) && in_array((int) $binding, [1, 2], true)) {
                        $lockedProductIds[] = (int) $binding;
                    }
                }
            }
        });

        // Request with reversed order: Product 2 first, then Product 1
        $this->postJson('/checkout', [
            'order_number' => 'ORD-104',
            'items' => [
                ['product_id' => 2, 'quantity' => 1],
                ['product_id' => 1, 'quantity' => 1],
            ],
        ])->assertOk();

        // The application MUST acquire locks sorted by ID ascending [1, 2],
        // preventing circular wait-for graph conditions.
        $distinctOrder = array_values(array_unique($lockedProductIds));
        $this->assertSame([1, 2], $distinctOrder, 'Locks must be acquired in ascending ID order to prevent deadlocks.');
    }
}
