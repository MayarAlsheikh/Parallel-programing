<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;

Route::get('/', function () {
    return view('welcome');
});
use Illuminate\Support\Facades\Redis;

Route::get('/test-redis', function () {
    try {
        Redis::set('test_key', 'Redis is working!');
        return Redis::get('test_key');
    } catch (\Exception $e) {
        return 'Redis connection failed: ' . $e->getMessage();
    }
});
Route::post('/checkout/bad', [CheckoutController::class, 'checkoutWithRaceCondition']);
