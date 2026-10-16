<?php

use App\Models\Product;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

Artisan::command('demo:deadlock {--pairs=10} {--url=http://127.0.0.1:8000/checkout} {--delay=50}', function () {
    $pairs = (int) $this->option('pairs');
    $url = $this->option('url');
    $delay = (int) $this->option('delay');

    $statuses = [];
    $deadlockErrors = 0;
    $successOrders = 0;

    $this->info("Menjalankan demo deadlock: {$pairs} pasang request konkuren ke {$url}...");

    for ($i = 1; $i <= $pairs; $i++) {
        $orderA = 'ORD-A-'.Str::upper(Str::random(6));
        $orderB = 'ORD-B-'.Str::upper(Str::random(6));

        // Request A: mengunci Produk 1 lalu Produk 2
        $payloadA = [
            'order_number' => $orderA,
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
                ['product_id' => 2, 'quantity' => 1],
            ],
            'delay_ms' => $delay,
        ];

        // Request B: urutan terbalik, mengunci Produk 2 lalu Produk 1
        $payloadB = [
            'order_number' => $orderB,
            'items' => [
                ['product_id' => 2, 'quantity' => 1],
                ['product_id' => 1, 'quantity' => 1],
            ],
            'delay_ms' => $delay,
        ];

        $responses = Http::pool(fn (Pool $pool) => [
            $pool->acceptJson()->post($url, $payloadA),
            $pool->acceptJson()->post($url, $payloadB),
        ]);

        foreach ($responses as $response) {
            if ($response instanceof Response) {
                $status = $response->status();
                $statuses[] = $status;

                if ($status === 200) {
                    $successOrders++;
                } else {
                    $body = $response->body();
                    if (str_contains($body, '1213') || str_contains($body, 'Deadlock found') || str_contains($body, 'Serialization failure')) {
                        $deadlockErrors++;
                    }
                }
            } else {
                $statuses[] = 'error';
            }
        }
    }

    $totalRequests = $pairs * 2;
    $this->line('Status HTTP: '.json_encode(array_count_values($statuses)));
    $this->line("Transaksi sukses (200): {$successOrders} dari {$totalRequests}");
    $this->line("Transaksi gagal karena deadlock (1213): {$deadlockErrors} dari {$totalRequests}");
});
