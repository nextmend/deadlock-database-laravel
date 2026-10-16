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

            // Bug: lock rows one by one in whatever order the client sent them
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock < $item['quantity']) {
                    throw new RuntimeException("Stok {$product->name} tidak mencukupi.");
                }

                $product->decrement('stock', $item['quantity']);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                ]);

                // Simulates processing work (e.g. calculation, payload preparation) while holding the row lock
                if (! empty($validated['delay_ms'])) {
                    usleep($validated['delay_ms'] * 1000);
                }
            }

            return $order;
        });

        return response()->json([
            'message' => 'Checkout berhasil',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }
}
