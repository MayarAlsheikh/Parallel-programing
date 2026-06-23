<?php

namespace App\Http\Controllers;

use App\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InventoryController extends Controller
{
    public function buyWithoutLock(Request $request)
    {
        $request->validate([
            'store_product_id' => 'required',
            'quantity' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();

        try {

            $product = StoreProduct::findOrFail(
                $request->store_product_id
            );

            if ($product->quantity < $request->quantity) {

                DB::rollBack();

                return response()->json([
                    'message' => 'Out of stock'
                ], 400);
            }


            $product->quantity -= $request->quantity;

            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Purchase completed',
                'remaining_quantity' => $product->quantity
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function buyWithDistributedLock(Request $request)
    {
        $request->validate([
            'store_product_id' => 'required',
            'quantity' => 'required|integer|min:1'
        ]);

        $lock = Cache::lock(
            'inventory_' . $request->store_product_id,
            1
        );

        if (!$lock->get()) {

            return response()->json([
                'message' => 'Product currently locked'
            ], 423);
        }

        try {

            DB::beginTransaction();

            $product = StoreProduct::findOrFail(
                $request->store_product_id
            );

            if ($product->quantity < $request->quantity) {

                DB::rollBack();

                return response()->json([
                    'message' => 'Out of stock'
                ], 400);
            }


            $product->quantity -= $request->quantity;

            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Purchase completed',
                'remaining_quantity' => $product->quantity
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);

        } finally {

            $lock->release();
        }
    }
}