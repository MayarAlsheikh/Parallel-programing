<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\StoreProduct;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Exception;

class CheckoutAcidController extends Controller
{
    

    public function checkoutWithoutAcid(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        // 1. Calculate price
        $price = Product::find($request->product_id)->price;
        $total_price = $price * $request->quantity;

        // 2. Find product in the specific store
        $storeProduct = StoreProduct::where('store_id', $request->store_id)
            ->where('product_id', $request->product_id)
            ->firstOrFail();

        if ($storeProduct->quantity < $request->quantity) {
            return response()->json(['message' => 'Out of stock'], 400);
        }

        // 3. Deduct inventory immediately
        $storeProduct->quantity -= $request->quantity;
        $storeProduct->save(); // ⚠️ DANGER: Inventory saved without a transaction.

        // 4. Simulate a random failure
        if ($request->has('simulate_error') && $request->simulate_error == true) {
            // Error stops execution. Inventory was deducted, but the order is never created!
            return response()->json(['message' => 'Payment Failed! Inventory permanently lost.'], 500);
        }

        // 5. Create the order
        Order::create([
            'user_id' => $request->user_id,
            'store_id' => $request->store_id,
            'total_price' => $total_price,
            'status' => 'paid'
        ]);

        return response()->json([
            'message' => 'Checkout complete (No ACID)',
            'remaining_quantity' => $storeProduct->quantity
        ]);
    }


    //==============================================================================

    
    public function checkoutWithAcid(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            // Begin the ACID Transaction
            DB::beginTransaction();

            $price = Product::find($request->product_id)->price;
            $total_price = $price * $request->quantity;

            // ISOLATION: lockForUpdate() prevents other requests from modifying this row.
            $storeProduct = StoreProduct::where('store_id', $request->store_id)
                ->where('product_id', $request->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($storeProduct->quantity < $request->quantity) {
                DB::rollBack();
                return response()->json(['message' => 'Out of stock'], 400);
            }

            // Deduct inventory
            $storeProduct->quantity -= $request->quantity;
            $storeProduct->save();

            // Simulate the same random failure
            if ($request->has('simulate_error') && $request->simulate_error == true) {
                throw new Exception("Payment Failed!");
            }

            // Create the order
            Order::create([
                'user_id' => $request->user_id,
                'store_id' => $request->store_id,
                'total_price' => $total_price,
                'status' => 'paid'
            ]);

            // DURABILITY & ATOMICITY: Save everything at once safely.
            DB::commit();

            return response()->json([
                'message' => 'Checkout complete (ACID Secure)',
                'remaining_quantity' => $storeProduct->quantity
            ]);
        } catch (Exception $e) {
            // ATOMICITY: An error happened! Undo the inventory deduction!
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage() . ' Transaction safely rolled back.'
            ], 500);
        }
    }
}
