<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ProductCacheController;
use App\Http\Controllers\CheckoutAcidController;

use App\Http\Controllers\InventoryController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//الوظيفة 
Route::post('/checkout/bad', [CheckoutController::class, 'checkoutWithRaceCondition']);
Route::post('/checkout/safe-pessimistic', [CheckoutController::class, 'checkoutSafePessimistic']);
Route::post('/checkout/safe-optimistic', [CheckoutController::class, 'checkoutSafeOptimistic']);


Route::post('/checkout/resource-drain', [CheckoutController::class, 'checkoutWithResourceDrain']);
Route::post('/checkout/resource-drain2', [CheckoutController::class, 'checkoutWithResourceDrain'])->middleware('throttle:50,1');
// Route::post('/unsafe-buy', [OrderController::class, 'unsafeBuy']);

Route::post('/checkout/async', [CheckoutController::class, 'checkoutAsync']);
Route::post('/checkout/asyncwiththrottle', [CheckoutController::class, 'checkoutAsync'])->middleware('throttle:50,1');


Route::post('/admin/report/bad-batch', [CheckoutController::class, 'calculateDailySalesBadApi']);

Route::post('/admin/report/good-batch', [CheckoutController::class, 'triggerDailySalesBatch']);

Route::post('/checkout/single-node', [CheckoutController::class, 'checkoutSingleNode']);

Route::post('/checkout/load-distribution', [CheckoutController::class, 'checkoutWithLoadDistribution']);


//المشروع النهائي 

Route::get('/top-products/no-cache',[ProductCacheController::class, 'topSellingWithoutCache']);

Route::get('/top-products/cache',[ProductCacheController::class, 'topSellingWithCache']);

Route::delete('/top-products/cache',[ProductCacheController::class, 'clearCache']);

Route::post('/buy-without-lock',[InventoryController::class, 'buyWithoutLock']);
Route::post('/buy-with-lock',[InventoryController::class, 'buyWithDistributedLock']);


Route::post('/checkout/no-acid', [CheckoutAcidController::class, 'checkoutWithoutAcid']);
Route::post('/checkout/acid', [CheckoutAcidController::class, 'checkoutWithAcid']);






Route::get('/metrics', function () {
    $output = "";

    $routesList = Cache::get('metrics_registered_routes', []);
    foreach ($routesList as $item) {
        $route = $item['route'];
        $status = $item['status'];

        $finalStatus = $statusCode ?? $status;

        $counterKey = "metrics_requests_total_{$route}_{$finalStatus}";
        $count = Cache::get($counterKey, 0);
        $output .= "http_requests_total{route=\"$route\",status=\"$status\"} $count\n";

        $durationKey = "metrics_request_duration_seconds_{$route}";
        $duration = Cache::get($durationKey, 0);
        $output .= "http_request_duration_seconds{route=\"$route\"} $duration\n";
    }

    $clusterList = Cache::get('metrics_cluster_nodes', []);
    foreach ($clusterList as $item) {
        $server = $item['server'];
        $status = $item['status'];

        $counterKey = "metrics_cluster_total_{$server}_{$status}";
        $count = Cache::get($counterKey, 0);
        $output .= "cluster_requests_total{server=\"$server\",status=\"$status\"} $count\n";

        $durationKey = "metrics_cluster_duration_{$server}";
        $duration = Cache::get($durationKey, 0);
        $output .= "cluster_request_duration_seconds{server=\"$server\"} $duration\n";
    }

    return response($output, 200)->header('Content-Type', 'text/plain; version=0.0.4');
});



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
