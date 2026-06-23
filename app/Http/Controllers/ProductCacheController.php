<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductCacheController extends Controller
{
    public function topSellingWithoutCache()
    {
        $products = OrderItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_sold')
            )
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'source' => 'database_direct',
            'data' => $products
        ]);
    }

    public function topSellingWithCache()
    {
        try {

            $products = Cache::remember(
                'top_selling_products',
                300,
                function () {

                    return OrderItem::select(
                            'product_id',
                            DB::raw('SUM(quantity) as total_sold')
                        )
                        ->with('product')
                        ->groupBy('product_id')
                        ->orderByDesc('total_sold')
                        ->limit(10)
                        ->get();
                }
            );

            return response()->json([
                'status' => 'success',
                'source' => 'redis_cache',
                'data' => $products
            ]);

        } catch (Exception $e) {

            Log::error($e->getMessage());

            $products = OrderItem::select(
                    'product_id',
                    DB::raw('SUM(quantity) as total_sold')
                )
                ->with('product')
                ->groupBy('product_id')
                ->orderByDesc('total_sold')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'source' => 'database_failover',
                'data' => $products
            ]);
        }
    }

    public function clearCache()
    {
        Cache::forget('top_selling_products');

        return response()->json([
            'message' => 'Cache cleared successfully'
        ]);
    }
}