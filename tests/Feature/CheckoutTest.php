<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
