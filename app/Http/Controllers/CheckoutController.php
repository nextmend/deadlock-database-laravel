<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delay_ms' => 'nullable|integer',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $order = Order::create([
                'order_number' => $validated['order_number'],
                'status' => 'paid',
            ]);

            // Solusi 1: Petakan item dan urutkan ID produk secara konsisten (ascending)
            $itemsByProduct = collect($validated['items'])->keyBy('product_id');
            $sortedProductIds = $itemsByProduct->keys()->sort()->values();

            // Solusi 2: Kunci semua baris dengan satu query terurut (deterministic lock ordering)
            $products = Product::whereIn('id', $sortedProductIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($sortedProductIds as $productId) {
                $product = $products->get($productId);
                $item = $itemsByProduct->get($productId);

                if (! $product || $product->stock < $item['quantity']) {
                    throw new RuntimeException("Stok ".($product?->name ?? $productId)." tidak mencukupi.");
                }

                $product->decrement('stock', $item['quantity']);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                ]);

                if (! empty($validated['delay_ms'])) {
                    usleep($validated['delay_ms'] * 1000);
                }
            }

            return $order;
        }, 3); // Jaring pengaman: otomatis coba ulang hingga 3 kali jika terjadi deadlock transient

        return response()->json([
            'message' => 'Checkout berhasil',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }
}
