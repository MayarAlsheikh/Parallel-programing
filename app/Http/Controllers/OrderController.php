<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function unsafeBuy(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required',
                'store_product_id' => 'required',
                'quantity' => 'required|integer|min:1'
            ]);

            // بدون Lock
            $user = User::find($request->user_id);

            $storeProduct = StoreProduct::find($request->store_product_id);

            $quantity = $request->quantity;

            // قراءة الكمية
            if ($storeProduct->quantity < $quantity) {

                return response()->json([
                    'message' => 'Out of stock'
                ], 400);
            }

            $price = $storeProduct->product->price;

            $totalPrice = $price * $quantity;

            // قراءة الرصيد
            if ($user->wallet_balance < $totalPrice) {

                return response()->json([
                    'message' => 'Not enough balance'
                ], 400);
            }

            // محاكاة بوابة الدفع
            sleep(2);

            // خصم الرصيد
            $user->wallet_balance -= $totalPrice;
            $user->save();

            // خصم المخزون
            $storeProduct->quantity -= $quantity;
            $storeProduct->save();

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => $storeProduct->store_id,
                'total_price' => $totalPrice,
                'status' => 'paid'
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $storeProduct->product_id,
                'store_id' => $storeProduct->store_id,
                'quantity' => $quantity,
                'price_at_purchase' => $price
            ]);

            return response()->json([
                'message' => 'Unsafe payment completed',
                'remaining_stock' => $storeProduct->quantity
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
