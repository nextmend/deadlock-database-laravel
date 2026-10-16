<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Product::updateOrCreate(['id' => 1], [
            'sku' => 'KB-MECH-01',
            'name' => 'Mechanical Keyboard TKL',
            'stock' => 1000,
        ]);

        Product::updateOrCreate(['id' => 2], [
            'sku' => 'MS-WIRE-02',
            'name' => 'Wireless Gaming Mouse',
            'stock' => 1000,
        ]);
    }
}
