<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Product;
use App\Models\StoreProduct;


class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::create([
            'name' => 'Main Store',
            'city' => 'Damascus'

        ]);

        $product1 = Product::create([
            'name' => 'Laptop',
            'price' => 600
        ]);

        $product2 = Product::create([
            'name' => 'Phone',
            'price' => 300
        ]);

        StoreProduct::create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
            'quantity' => 100,
            'version' => 1
        ]);

        StoreProduct::create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
            'quantity' => 100,
            'version' => 1
        ]);
        $stores = [
            ['name' => 'دمشق المركزي', 'city' => 'Damascus'],
            ['name' => 'فرع حلب الشهباء', 'city' => 'Aleppo'],
            ['name' => 'مخازن حمص الكبرى', 'city' => 'Homs'],
            ['name' => 'فرع اللاذقية الساحلي', 'city' => 'Lattakia'],
            ['name' => 'مركز حماة التجاري', 'city' => 'Hama'],
            ['name' => 'متجر الشام الرقمي', 'city' => 'Damascus'],
            ['name' => 'فرع حلب الجديد', 'city' => 'Aleppo'],
            ['name' => 'مكتبة حمص الوطنية', 'city' => 'Homs'],
            ['name' => 'بوابة اللاذقية الإلكترونية', 'city' => 'Lattakia'],
            ['name' => 'العاصي ستور', 'city' => 'Hama'],
        ];

        foreach ($stores as &$store) {
            $store['created_at'] = now();
            $store['updated_at'] = now();
        }

        Store::insert($stores);
    }
}