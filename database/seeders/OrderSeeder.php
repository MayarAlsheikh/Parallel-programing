<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Store;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::pluck('id')->toArray();
        $storeIds = Store::pluck('id')->toArray();

        $ordersData = [];
        $totalOrders = 500;
        $batchSize = 2500; 

        for ($i = 1; $i <= $totalOrders; $i++) {
            $ordersData[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'store_id' => $storeIds[array_rand($storeIds)],
                'total_price' => rand(50, 2500),
                'status' => 'paid', 
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($i % $batchSize === 0) {
                DB::table('orders')->insert($ordersData);
                $ordersData = []; 
            }
        }

        if (!empty($ordersData)) {
            DB::table('orders')->insert($ordersData);
        }
    }
}