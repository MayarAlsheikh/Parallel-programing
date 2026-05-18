<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;

class StoreProductSeeder extends Seeder
{
    public function run(): void
    {
        $productNames = [
            'MacBook Pro 14', 'iPhone 15 Pro', 'Samsung Galaxy S24', 'Dell XPS 13', 'Asus ROG Strix',
            'Sony WH-1000XM5', 'AirPods Pro 2', 'iPad Air', 'Apple Watch Ultra', 'Logitech G Pro X',
            'PlayStation 5', 'Nintendo Switch OLED', 'Xbox Series X', 'Kindle Paperwhite', 'GoPro Hero 12',
            'Canon EOS R6', 'DJI Mini 4 Pro', 'Seagate Portable HDD 2TB', 'Anker PowerBank 20k', 'Razer DeathAdder',
            'LG UltraGear Monitor', 'Corsair Mechanical Keyboard', 'HyperX QuadCast Mic', 'SteelSeries Headset', 'TP-Link Wi-Fi 6 Router',
            'شاحن سريع 65 واط', 'كابل بيانات عالي السرعة', 'قاعدة تبريد لابتوب', 'حقيبة ظهر مضادة للماء', 'حامل هاتف ذكي للسيارة',
            'موزع يو إس بي متعدد', 'بطاقة ذاكرة 128 جيجا', 'سماعة بلوتوث رياضية', 'ماوس باد ألعاب عملاق', 'كاميرا ويب بدقة 4K',
            'ميكروفون لاسلكي ياقة', 'لوحة رسم رقمية', 'قرص تخزين داخلي 1TB SSD', 'مكبر صوت لاسلكي خارجي', 'نظارات ذكية لحماية العين',
            'منظم كابلات مكتبي', 'مصباح ليد ذكي للمكتب', 'لوحة مفاتيح لاسلكية مدمجة', 'قلم ذكي للشاشات', 'محول إشارة HDMI',
            'سوار رياضي ذكي', 'مروحة مكتب يو إس بي', 'غطاء حماية آيفون صلب', 'لاصقة حماية شاشة نانو', 'محفظة بطاقات مغناطيسية'
        ];

        $productsData = [];
        foreach ($productNames as $name) {
            $productsData[] = [
                'name' => $name,
                'price' => rand(20, 1500),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Product::insert($productsData);

        // جلب المعرفات لربط المخزون
        $storeIds = Store::pluck('id')->toArray();
        $productIds = Product::pluck('id')->toArray();

        $storeProductsData = [];
        foreach ($storeIds as $storeId) {
            foreach ($productIds as $productId) {
                $storeProductsData[] = [
                    'store_id' => $storeId,
                    'product_id' => $productId,
                    'quantity' => 1000000, 
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // CHANGE HERE: Use insertOrIgnore to safely skip existing rows
        foreach (array_chunk($storeProductsData, 100) as $chunk) {
            StoreProduct::insertOrIgnore($chunk);
        }
    }
}