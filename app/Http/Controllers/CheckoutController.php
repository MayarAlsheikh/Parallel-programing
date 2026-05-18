<?php

namespace App\Http\Controllers;

use App\Models\StoreProduct;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Exceptions\RaceConditionException;
use App\Jobs\ProcessOrderBilling;
use App\Jobs\ProcessDailySalesReportBatch;
use App\Models\DailySalesReport;
use Illuminate\Support\Facades\Cache;

class CheckoutController extends Controller
{

    public function checkoutWithRaceCondition(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);
            $user = User::find($request->user_id);
            $requestedQuantity = $request->quantity;
            $price = Product::find($request->product_id)->price;
            $total_price = $price * $requestedQuantity;
            $storeProduct = StoreProduct::where('store_id', $request->store_id)
                ->where('product_id', $request->product_id)
                ->select('*')
                ->firstOrFail();
            if ($storeProduct->quantity < $requestedQuantity) {
                return response()->json(['error' => 'Not enough stock'], 400);
            }
            usleep(50000);
            $storeProduct->quantity -= $requestedQuantity;
            $storeProduct->save();
            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => $request->store_id,
                'total_price' => $total_price,
                'status' => 'paid'
            ]);
            $user_wallet = User::find($user->id);
            if ($user_wallet->wallet_balance < $total_price) {
                return response()->json(['error' => 'Not enough balance in wallet'], 400);
            }
            $user_wallet->wallet_balance -= $total_price;
            $user_wallet->save();
            return response()->json([
                'message' => 'Order placed successfully bad api',
                'remaining_stock' => $storeProduct->quantity,
                'user_wallet_balance' => $user_wallet->wallet_balance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkoutSafePessimistic(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);
            $requestedQuantity = $request->quantity;
            $result = DB::transaction(function () use ($request, $requestedQuantity) {
                $user = User::where('id', $request->user_id)->lockForUpdate()->firstOrFail();
                $price = Product::find($request->product_id)->price;
                $total_price = $price * $requestedQuantity;
                if ($user->wallet_balance < $total_price) {
                    throw new \Exception("Not enough balance in wallet");
                }
                $storeProduct = StoreProduct::where('store_id', $request->store_id)
                    ->where('product_id', $request->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($storeProduct->quantity < $requestedQuantity) {
                    throw new \Exception("Not enough stock");
                }
                usleep(50000);
                $storeProduct->quantity -= $requestedQuantity;
                $storeProduct->save();
                $user->wallet_balance -= $total_price;
                $user->save();
                $order = Order::create([
                    'user_id' => $user->id,
                    'store_id' => $request->store_id,
                    'total_price' => $total_price,
                    'status' => 'paid'
                ]);
                return [
                    'remaining_stock' => $storeProduct->quantity,
                    'user_wallet_balance' => $user->wallet_balance
                ];
            });
            return response()->json([
                'message' => 'Order placed safely Pessimistic lock success',
                'remaining_stock' => $result['remaining_stock'],
                'user_wallet_balance' => $result['user_wallet_balance']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
    public function checkoutSafeOptimistic(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);
            $requestedQuantity = $request->quantity;

            $result = DB::transaction(function () use ($request, $requestedQuantity) {

                $user = User::findOrFail($request->user_id);

                $price = Product::find($request->product_id)->price;
                $total_price = $price * $requestedQuantity;

                if ($user->wallet_balance < $total_price) {
                    throw new \Exception("Not enough balance in wallet");
                }
                $storeProduct = StoreProduct::where('store_id', $request->store_id)
                    ->where('product_id', $request->product_id)
                    ->firstOrFail();
                if ($storeProduct->quantity < $requestedQuantity) {
                    throw new \Exception("Not enough stock");
                }
                $currentWalletBalance = $user->wallet_balance;
                $currentVersion = $storeProduct->version;
                usleep(50000);

                $updatedStock = DB::table('store_products')
                    ->where('id', $storeProduct->id)
                    ->where('version', $currentVersion)
                    ->update([
                        'quantity' => $storeProduct->quantity - $requestedQuantity,
                        'version' => $currentVersion + 1,
                        'updated_at' => now()
                    ]);
                if (!$updatedStock) {
                    throw new RaceConditionException("Conflict detected on stock.");
                }
                $updatedWallet = DB::table('users')
                    ->where('id', $user->id)
                    ->where('wallet_balance', $currentWalletBalance)
                    ->update([
                        'wallet_balance' => $currentWalletBalance - $total_price,
                        'updated_at' => now()
                    ]);
                if (!$updatedWallet) {
                    throw new RaceConditionException("Conflict detected on wallet.");
                }
                $order = Order::create([
                    'user_id' => $user->id,
                    'store_id' => $request->store_id,
                    'total_price' => $total_price,
                    'status' => 'paid'
                ]);

                return [
                    'remaining_stock' => $storeProduct->quantity - $requestedQuantity,
                    'user_wallet_balance' => $currentWalletBalance - $total_price
                ];
            });

            return response()->json([
                'message' => 'Order placed safely optimistic lock success',
                'remaining_stock' => $result['remaining_stock'],
                'user_wallet_balance' => $result['user_wallet_balance']
            ], 200);
        } catch (RaceConditionException $e) {
            return response()->json([
                'error' => 'Conflict detected. Another transaction modified this resource. Please try again.'
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }





    public function checkoutWithResourceDrain(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);
            $result = DB::transaction(function () use ($request) {
                $user = User::where('id', $request->user_id)->lockForUpdate()->firstOrFail();
                $storeProduct = StoreProduct::where('store_id', $request->store_id)
                    ->where('product_id', $request->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $price = Product::find($request->product_id)->price;
                $total_price = $price * $request->quantity;
                if ($user->wallet_balance < $total_price || $storeProduct->quantity < $request->quantity) {
                    throw new \Exception("Insufficient stock or balance");
                }
                $storeProduct->quantity -= $request->quantity;
                $storeProduct->save();

                $user->wallet_balance -= $total_price;
                $user->save();

                return Order::create([
                    'user_id' => $user->id,
                    'store_id' => $request->store_id,
                    'total_price' => $total_price,
                    'status' => 'paid'
                ]);
            });
            $heavyLoadData = [];
            for ($i = 0; $i < 500000; $i++) {
                $heavyLoadData[] = md5($i . time());
            }
            usleep(200000);
            unset($heavyLoadData);
            return response()->json([
                'message' => 'Order placed but system resources were heavily drained!',
                'order_id' => $result->id
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function checkoutAsync(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);

            $result = DB::transaction(function () use ($request) {
                $user = User::where('id', $request->user_id)->lockForUpdate()->firstOrFail();
                $storeProduct = StoreProduct::where('store_id', $request->store_id)
                    ->where('product_id', $request->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $price = Product::find($request->product_id)->price;
                $total_price = $price * $request->quantity;

                if ($user->wallet_balance < $total_price || $storeProduct->quantity < $request->quantity) {
                    throw new \Exception("Insufficient stock or balance");
                }

                $storeProduct->quantity -= $request->quantity;
                $storeProduct->save();

                $user->wallet_balance -= $total_price;
                $user->save();

                return Order::create([
                    'user_id' => $user->id,
                    'store_id' => $request->store_id,
                    'total_price' => $total_price,
                    'status' => 'paid'
                ]);
            });

            ProcessOrderBilling::dispatch($result);

            return response()->json([
                'message' => 'Order placed successfully. Billing is being processed in the background.',
                'order_id' => $result->id
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    public function calculateDailySalesBadApi(Request $request)
    {
        $date = $request->get('date', now()->toDateString());

        $orders = Order::whereDate('created_at', $date)
            ->where('status', 'paid')
            ->get();

        $salesBuffer = [];

        foreach ($orders as $order) {
            $storeId = $order->store_id;

            if (!isset($salesBuffer[$storeId])) {
                $salesBuffer[$storeId] = ['total_sales' => 0, 'orders_count' => 0];
            }

            $salesBuffer[$storeId]['total_sales'] += $order->total_price;
            $salesBuffer[$storeId]['orders_count'] += 1;
        }

        // الحفظ في قاعدة البيانات
        foreach ($salesBuffer as $storeId => $data) {
            DailySalesReport::updateOrCreate(
                ['store_id' => $storeId, 'report_date' => $date],
                ['total_sales' => $data['total_sales'], 'orders_count' => $data['orders_count']]
            );
        }

        return response()->json([
            'message' => 'Bad API Finished. (If it did not crash the server!)',
            'processed_orders' => count($orders)
        ], 200);
    }

    public function triggerDailySalesBatch(Request $request)
    {
        try {
            $date = $request->get('date', now()->toDateString());

            ProcessDailySalesReportBatch::dispatch($date);

            return response()->json([
                'message' => "Daily sales batch job dispatched successfully for date: {$date}."
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    public function checkoutSingleNode(Request $request)
    {
        $startTime = microtime(true);
        $targetServer = 'Server_Node_1';

        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);

            $result = DB::transaction(function () use ($request) {
                $user = User::where('id', $request->user_id)->lockForUpdate()->firstOrFail();
                $storeProduct = StoreProduct::where('store_id', $request->store_id)
                    ->where('product_id', $request->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($user->wallet_balance < ($ProductPrice = Product::find($request->product_id)->price * $request->quantity) || $storeProduct->quantity < $request->quantity) {
                    throw new \Exception("Insufficient stock or balance");
                }

                $storeProduct->quantity -= $request->quantity;
                $storeProduct->save();

                $user->wallet_balance -= $ProductPrice;
                $user->save();

                return Order::create([
                    'user_id' => $user->id,
                    'store_id' => $request->store_id,
                    'total_price' => $ProductPrice,
                    'status' => 'paid'
                ]);
            });

            usleep(30000);

            $duration = microtime(true) - $startTime;
            $this->recordLoadDistributionMetrics($targetServer, '200', $duration);

            return response()->json([
                'message' => 'Processed via Single Static Server (No Load Balancing).',
                'handled_by' => $targetServer,
                'order_id' => $result->id
            ], 200);
        } catch (\Exception $e) {
            $this->recordLoadDistributionMetrics($targetServer, '400', microtime(true) - $startTime);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function checkoutWithLoadDistribution(Request $request)
    {
        $startTime = microtime(true);

        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_id' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);

            $requestCounter = Cache::increment('load_balancer_request_count');
            $targetServer = ($requestCounter % 2 === 0) ? 'Server_Node_1' : 'Server_Node_2';

            $result = DB::transaction(function () use ($request) {
                $user = User::where('id', $request->user_id)->lockForUpdate()->firstOrFail();
                $storeProduct = StoreProduct::where('store_id', $request->store_id)
                    ->where('product_id', $request->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $price = Product::find($request->product_id)->price;
                $total_price = $price * $request->quantity;

                if ($user->wallet_balance < $total_price || $storeProduct->quantity < $request->quantity) {
                    throw new \Exception("Insufficient stock or balance");
                }

                $storeProduct->quantity -= $request->quantity;
                $storeProduct->save();

                $user->wallet_balance -= $total_price;
                $user->save();

                return Order::create([
                    'user_id' => $user->id,
                    'store_id' => $request->store_id,
                    'total_price' => $total_price,
                    'status' => 'paid'
                ]);
            });

            usleep(30000);

            $duration = microtime(true) - $startTime;
            $this->recordLoadDistributionMetrics($targetServer, '200', $duration);

            return response()->json([
                'message' => 'Order distributed and processed successfully via cluster.',
                'handled_by' => $targetServer,
                'order_id' => $result->id
            ], 200);
        } catch (\Exception $e) {
            $requestCounter = Cache::get('load_balancer_request_count', 0);
            $targetServer = ($requestCounter % 2 === 0) ? 'Server_Node_1' : 'Server_Node_2';
            $this->recordLoadDistributionMetrics($targetServer, '400', microtime(true) - $startTime);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    private function recordLoadDistributionMetrics(string $server, string $status, float $duration)
    {
        $clusterList = Cache::get('metrics_cluster_nodes', []);
        $exists = false;
        foreach ($clusterList as $item) {
            if ($item['server'] === $server && $item['status'] === $status) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $clusterList[] = ['server' => $server, 'status' => $status];
            Cache::put('metrics_cluster_nodes', $clusterList);
        }

        Cache::increment("metrics_cluster_total_{$server}_{$status}");
        Cache::put("metrics_cluster_duration_{$server}", $duration);
    }
}
