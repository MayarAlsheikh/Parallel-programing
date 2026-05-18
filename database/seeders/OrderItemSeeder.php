<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;

class OrderItemSeeder extends Seeder
{
    public function run(): void
    {
        // جلب المنتجات المتاحة لربطها بالتفاصيل واقتباس أسعارها
        $products = Product::select('id', 'price')->get()->toArray();
        
        if (empty($products)) {
            return;
        }

        $orderItemsData = [];
        $batchSize = 2500; // الحفظ على دفعات لضمان استقرار الذاكرة تماماً
        $counter = 0;

        // الكفاءة الهندسية: نمر على الـ 50,000 طلب على دفعات (Chunks) من قاعدة البيانات مباشرة
        DB::table('orders')->select('id', 'store_id')->chunkById(1000, function ($orders) use (&$orderItemsData, &$counter, $products, $batchSize) {
            foreach ($orders as $order) {
                // نختار عشوائياً عدد المنتجات داخل هذا الطلب (مثلاً منتج واحد أو منتجين)
                $itemsCount = rand(1, 2);

                for ($j = 0; $j < $itemsCount; $j++) {
                    $randomProduct = $products[array_rand($products)];
                    $quantity = rand(1, 3);

                    $orderItemsData[] = [
                        'order_id' => $order->id,
                        'product_id' => $randomProduct['id'],
                        'store_id' => $order->store_id,
                        'quantity' => $quantity,
                        'price_at_purchase' => $randomProduct['price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $counter++;

                    // إذا وصلنا للحد الأقصى للدفعة، نقوم بالحفظ الفوري وتحرير المصفوفة
                    if ($counter % $batchSize === 0) {
                        DB::table('order_items')->insert($orderItemsData);
                        $orderItemsData = [];
                    }
                }
            }
        });

        // حفظ ما تبقى من تفاصيل إن وجدت
        if (!empty($orderItemsData)) {
            DB::table('order_items')->insert($orderItemsData);
        }
    }
}